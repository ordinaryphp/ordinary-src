<?php

declare(strict_types=1);

namespace Ordinary\Log\Processor;

use Ordinary\Log\ImmutableLogEntryInterface;
use Ordinary\Log\LogEntry;
use Ordinary\Log\LogEntryInterface;
use Ordinary\Log\LogProcessorInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Adds HTTP request details to every log item.
 *
 * Accepts a PSR-7 {@see ServerRequestInterface} or a raw server params array.
 * When a PSR-7 request is supplied, `getServerParams()` forms the base context
 * and PSR-7 native methods (URI, method, headers) override each field they can
 * express more precisely. Server params therefore act as a complete fallback for
 * any field whose PSR-7 accessor returns an empty value. When `null` is passed
 * no context is added.
 *
 * ```php
 * // PSR-7 request (framework-friendly)
 * $logger->addProcessor(new WebProcessor($request));
 *
 * // Raw server params array (useful in tests or CLI)
 * $logger->addProcessor(new WebProcessor(['REQUEST_URI' => '/test', 'REMOTE_ADDR' => '127.0.0.1']));
 * ```
 *
 * Added context keys (only populated when the corresponding data is available):
 * - `request.url`        — full request URI / `REQUEST_URI`
 * - `request.ip`         — `REMOTE_ADDR` (server params)
 * - `request.method`     — HTTP method / `REQUEST_METHOD`
 * - `request.server`     — URI host / `SERVER_NAME`
 * - `request.referrer`   — `Referer` header / `HTTP_REFERER`
 * - `request.user_agent` — `User-Agent` header / `HTTP_USER_AGENT`
 *
 * Pass additional server param keys via `$extraFields` to include them under
 * `request.<lowercase_key>`.
 */
final readonly class WebProcessor implements LogProcessorInterface
{
    /**
     * @param ServerRequestInterface|array<string, mixed>|null $request
     *                                                                  PSR-7 server request, a server params array, or null to add no context.
     * @param list<string> $extraFields Additional server param keys to include.
     */
    public function __construct(
        private ServerRequestInterface|array|null $request = null,
        private array $extraFields = [],
    ) {}

    public function process(LogEntryInterface $logItem): LogEntryInterface
    {
        $context = match (true) {
            $this->request instanceof ServerRequestInterface => $this->buildFromRequest($this->request),
            \is_array($this->request) => $this->buildContext($this->request),
            default => [],
        };

        if ($context === []) {
            return $logItem;
        }

        if ($logItem instanceof ImmutableLogEntryInterface) {
            return $logItem->withContext($context);
        }

        return new LogEntry(
            $logItem->level,
            $logItem->message,
            $logItem->dateTime,
            \array_merge($logItem->context, $context),
        );
    }

    /**
     * Builds context from a PSR-7 request.
     *
     * Server params provide the base (via {@see buildContext()}); PSR-7 native
     * methods then override each field they can express more precisely. Any
     * server param key not covered by a PSR-7 accessor (e.g. REMOTE_ADDR,
     * extra fields) is therefore included automatically as a fallback.
     *
     * @return array<string, mixed>
     */
    private function buildFromRequest(ServerRequestInterface $request): array
    {
        /** @var array<string, mixed> $serverParams */
        $serverParams = $request->getServerParams();
        $context = $this->buildContext($serverParams);

        $uri = $request->getUri();

        $url = (string) $uri;
        if ($url !== '') {
            $context['request.url'] = $url;
        }

        $method = $request->getMethod();
        if ($method !== '') {
            $context['request.method'] = $method;
        }

        $host = $uri->getHost();
        if ($host !== '') {
            $context['request.server'] = $host;
        }

        $referrer = $request->getHeaderLine('Referer');
        if ($referrer !== '') {
            $context['request.referrer'] = $referrer;
        }

        $userAgent = $request->getHeaderLine('User-Agent');
        if ($userAgent !== '') {
            $context['request.user_agent'] = $userAgent;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $server
     *
     * @return array<string, mixed>
     */
    private function buildContext(array $server): array
    {
        $context = [];

        $map = [
            'request.url'        => 'REQUEST_URI',
            'request.ip'         => 'REMOTE_ADDR',
            'request.method'     => 'REQUEST_METHOD',
            'request.server'     => 'SERVER_NAME',
            'request.referrer'   => 'HTTP_REFERER',
            'request.user_agent' => 'HTTP_USER_AGENT',
        ];

        foreach ($map as $contextKey => $serverKey) {
            if (isset($server[$serverKey])) {
                $context[$contextKey] = $server[$serverKey];
            }
        }

        foreach ($this->extraFields as $field) {
            if (isset($server[$field])) {
                $context['request.' . \strtolower($field)] = $server[$field];
            }
        }

        return $context;
    }
}
