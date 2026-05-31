<?php

declare(strict_types=1);

namespace Ordinary\Log;

use Ordinary\Log\FailureHandler\ErrorLogFailureHandler;
use Ordinary\Log\Internal\LogGroup;
use Ordinary\Log\Psr\PsrLoggerAdapter;
use Psr\Clock\ClockInterface;

/**
 * Primary logger. Fans log items out to one or more drivers, organised into
 * named groups. Each group has an optional matcher; each driver within a group
 * has its own optional matcher that acts as an additional AND filter.
 *
 * A `default` group is always present with no matcher and cannot be removed.
 * Drivers added without specifying a group land in `default`.
 *
 * ```php
 * $logger = new Logger(channel: 'payment');
 * $logger->addProcessor(new CallableProcessor(
 *     fn(ImmutableLogEntryInterface $item) => $item->withContext(['request_id' => $requestId]),
 * ));
 * $logger->add(new StreamDriver(STDOUT, new JsonFormatter()));
 * $logger->info('Charge processed', ['amount' => 99.00]);
 * ```
 */
final class Logger implements LoggerInterface
{
    use LoggerTrait;

    private const string DEFAULT_GROUP = 'default';

    /** @var array<string, LogGroup> */
    private array $groups = [];

    /** @var list<LogProcessorInterface> */
    private array $processors = [];

    /**
     * @param string $channel
     *                        Optional channel name stamped on every log item before dispatch and
     *                        processors run. Appears as a top-level `"channel"` field in
     *                        {@see \Ordinary\Log\JsonFormatter} output and as the `{channel}`
     *                        placeholder in {@see \Ordinary\Log\TextFormatter}.
     * @param null|\Closure(\Closure(): void): void $dispatcher
     *                                                          When provided, each driver call is passed to this closure as a
     *                                                          zero-argument operation. Use this to queue writes onto an event
     *                                                          loop. Drivers implementing {@see SynchronousDriverInterface} are
     *                                                          always invoked immediately, bypassing the dispatcher.
     * @param LogFailureHandlerInterface $onFailure
     *                                              Called when a driver throws. Defaults to {@see ErrorLogFailureHandler},
     *                                              which writes a message to PHP's error log. Pass
     *                                              {@see \Ordinary\Log\FailureHandler\NoOpFailureHandler} to silence failures.
     * @param ClockInterface $clock
     *                              Clock used to timestamp log items created via the named helper methods
     *                              (info(), error(), etc.). Defaults to {@see UtcClock}. Inject a test
     *                              double to control timestamps in unit tests.
     */
    public function __construct(
        public readonly string $channel = '',
        private readonly ?\Closure $dispatcher = null,
        private readonly LogFailureHandlerInterface $onFailure = new ErrorLogFailureHandler(),
        ClockInterface $clock = new UtcClock(),
    ) {
        $this->clock = $clock;
        $this->groups[self::DEFAULT_GROUP] = new LogGroup(self::DEFAULT_GROUP);
    }

    /**
     * Registers a processor that transforms every log item before dispatch.
     *
     * Processors run in registration order after the channel is stamped and
     * before group/driver matching begins. Use {@see CallableProcessor}
     * to wrap an inline closure.
     */
    public function addProcessor(LogProcessorInterface $processor): void
    {
        $this->processors[] = $processor;
    }

    /**
     * Adds a named log group with an optional matcher.
     *
     * All drivers added to this group will only receive log items that pass
     * the group matcher. Per-driver matchers apply as an additional AND filter.
     *
     * @throws \InvalidArgumentException if a group with this ID already exists
     */
    public function addGroup(string $id, ?LogMatcherInterface $matcher = null): void
    {
        if (isset($this->groups[$id])) {
            throw new \InvalidArgumentException(
                \sprintf('Log group "%s" already exists', $id),
            );
        }

        $this->groups[$id] = new LogGroup($id, $matcher);
    }

    /**
     * Removes a named log group and all drivers registered to it.
     *
     * @throws \InvalidArgumentException if the group is "default" or does not exist
     */
    public function removeGroup(string $id): void
    {
        if ($id === self::DEFAULT_GROUP) {
            throw new \InvalidArgumentException('Cannot remove the default log group');
        }

        if (!isset($this->groups[$id])) {
            throw new \InvalidArgumentException(
                \sprintf('Log group "%s" does not exist', $id),
            );
        }

        unset($this->groups[$id]);
    }

    /**
     * Registers a driver with a group.
     *
     * @param LogMatcherInterface|null $matcher
     *                                          Applied after the group matcher. The driver only receives items
     *                                          that pass both the group matcher AND this matcher.
     * @param string $group
     *                      Defaults to "default". The group must exist; call addGroup() first.
     *
     * @throws \InvalidArgumentException if the group does not exist
     */
    public function add(
        LogDriverInterface $driver,
        ?LogMatcherInterface $matcher = null,
        string $group = self::DEFAULT_GROUP,
    ): void {
        if (!isset($this->groups[$group])) {
            throw new \InvalidArgumentException(
                \sprintf('Log group "%s" does not exist. Call addGroup() first.', $group),
            );
        }

        $this->groups[$group]->addDriver($driver, $matcher);
    }

    /**
     * Flushes all registered drivers that implement FlushableInterface.
     *
     * Call this at the end of each request or job to drain any buffered items.
     * Every flushable driver is attempted regardless of whether a previous flush
     * threw; the first exception, if any, is re-thrown after all drivers have
     * been attempted.
     */
    public function flush(): void
    {
        $firstFailure = null;

        foreach ($this->groups as $group) {
            foreach ($group->drivers as [$driver]) {
                if (!($driver instanceof FlushableInterface)) {
                    continue;
                }

                try {
                    $driver->flush();
                } catch (\Throwable $e) {
                    $firstFailure ??= $e;
                }
            }
        }

        if ($firstFailure instanceof \Throwable) {
            throw $firstFailure;
        }
    }

    /**
     * Returns a PSR-3 compatible adapter wrapping this logger.
     *
     * Use this when passing the logger to a library or framework that
     * type-hints {@see \Psr\Log\LoggerInterface} rather than ordinary/log's
     * native {@see LoggerInterface}.
     *
     * ```php
     * $psrLogger = $logger->toPsr();
     * $someLibrary->setLogger($psrLogger);
     * ```
     */
    public function toPsr(): PsrLoggerAdapter
    {
        return new PsrLoggerAdapter($this);
    }

    public function log(LogEntryInterface $logItem): void
    {
        if ($this->channel !== '') {
            $context = $logItem->context;
            $context[LogEntryInterface::RESERVED_CHANNEL] = $this->channel;
            $logItem = new LogEntry($logItem->level, $logItem->message, $logItem->dateTime, $context);
        }

        foreach ($this->processors as $processor) {
            $logItem = $processor->process($logItem);
        }

        foreach ($this->groups as $group) {
            if (!$group->matches($logItem)) {
                continue;
            }

            foreach ($group->drivers as [$driver, $driverMatcher]) {
                if ($driverMatcher !== null && !$driverMatcher->matches($logItem)) {
                    continue;
                }

                $this->invoke($driver, $logItem);
            }
        }
    }

    private function invoke(LogDriverInterface $driver, LogEntryInterface $logItem): void
    {
        $operation = function () use ($driver, $logItem): void {
            try {
                $driver->handleLog($logItem);
            } catch (\Throwable $throwable) {
                $this->onFailure->handleLogFailure(
                    new LogFailureException($logItem, $throwable, $driver),
                );
            }
        };

        if ($this->dispatcher instanceof \Closure && !($driver instanceof SynchronousDriverInterface)) {
            ($this->dispatcher)($operation);
        } else {
            $operation();
        }
    }
}
