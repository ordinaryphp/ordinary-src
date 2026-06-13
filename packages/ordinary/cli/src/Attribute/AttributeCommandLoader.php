<?php

declare(strict_types=1);

namespace Ordinary\Cli\Attribute;

use Ordinary\Cli\Argument\ArgumentMode;
use Ordinary\Cli\Command\CommandBuilder;
use Ordinary\Cli\CommandRouterInterface;
use Ordinary\Cli\Console;
use Ordinary\Cli\Exception\InvalidArgumentException;
use Ordinary\Cli\Input\ParsedInputInterface;
use Ordinary\Cli\Option\OptionScope;
use Ordinary\Cli\Option\OptionType;

/**
 * Discovers and registers commands declared via #[Command], #[CommandOption],
 * and #[CommandArgument] attributes on classes, methods, and functions.
 *
 * Handler resolution: if a factory closure is provided, class instances are
 * resolved via $factory(ClassName::class). Without a factory, classes
 * are instantiated with new ClassName() — a zero-argument constructor is required.
 *
 * To integrate with a PSR-11 container, pass `$container->get(...)` as the factory:
 *
 * ```php
 * $loader = new AttributeCommandLoader($router, fn(string $class) => $container->get($class));
 * ```
 */
final readonly class AttributeCommandLoader
{
    public function __construct(
        private CommandRouterInterface $router,
        /** @var (\Closure(class-string): object)|null */
        private ?\Closure $factory = null,
    ) {}

    /**
     * Scan a directory recursively for PHP files and register all commands found via attributes.
     *
     * Classes and functions are discovered via token parsing (no file execution).
     * Classes or functions with no #[Command] attribute are silently skipped.
     *
     * @throws InvalidArgumentException if $directory does not exist or is not readable
     */
    public function loadDirectory(string $directory): void
    {
        if (!\is_dir($directory) || !\is_readable($directory)) {
            throw new InvalidArgumentException(
                \sprintf('Directory "%s" does not exist or is not readable', $directory),
            );
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();

            if ($realPath === false) {
                continue;
            }

            [$classNames, $functionNames] = $this->extractNames($realPath);

            foreach ($classNames as $className) {
                try {
                    $this->loadClass($className);
                } catch (\ReflectionException) {
                    // Class could not be reflected — skip silently
                }
            }

            foreach ($functionNames as $functionName) {
                try {
                    $this->loadFunction($functionName);
                } catch (\ReflectionException) {
                    // Function could not be reflected — skip silently
                }
            }
        }
    }

    /**
     * Reflect on a class and register all commands declared via attributes.
     *
     * If the class itself has #[Command], it is registered as a command.
     * Public methods with #[Command] are registered as subcommands; when the
     * class also carries #[Command], the class-level command name is the implicit parent.
     *
     * @param class-string $className
     *
     * @throws \ReflectionException
     */
    public function loadClass(string $className): void
    {
        $rc = new \ReflectionClass($className);

        $classCommandAttrs = $rc->getAttributes(Command::class);
        $classCommand = $classCommandAttrs !== [] ? $classCommandAttrs[0]->newInstance() : null;

        if ($classCommand !== null) {
            $builder = $this->resolveBuilder($classCommand->parent, $classCommand->name);
            $builder->description($classCommand->description);
            $this->applyOptionAttributes($rc->getAttributes(CommandOption::class), $builder);
            $this->applyArgumentAttributes($rc->getAttributes(CommandArgument::class), $builder);

            if ($rc->hasMethod('__invoke')) {
                $instance = $this->resolveInstance($className);
                $closure = $rc->getMethod('__invoke')->getClosure($instance);
                /** @var \Closure(ParsedInputInterface, Console): int $handler */
                $handler = $closure;
                $builder->handler($handler);
            }
        }

        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $this->loadMethod($className, $method->getName(), $classCommand);
        }
    }

    /**
     * Reflect on a specific method and register the command declared on it.
     *
     * @param class-string $className
     *
     * @throws \ReflectionException
     */
    public function loadMethod(
        string $className,
        string $methodName,
        ?Command $implicitParentCommand = null,
    ): void {
        $rm = new \ReflectionMethod($className, $methodName);
        $methodCommandAttrs = $rm->getAttributes(Command::class);

        if ($methodCommandAttrs === []) {
            return;
        }

        /** @var Command $methodCommand */
        $methodCommand = $methodCommandAttrs[0]->newInstance();

        $parent = $implicitParentCommand instanceof Command
            ? [$implicitParentCommand->name]
            : $methodCommand->parent;

        $builder = $this->resolveBuilder($parent, $methodCommand->name);
        $builder->description($methodCommand->description);
        $this->applyOptionAttributes($rm->getAttributes(CommandOption::class), $builder);
        $this->applyArgumentAttributes($rm->getAttributes(CommandArgument::class), $builder);

        $instance = $this->resolveInstance($className);
        $closure = $rm->getClosure($instance);
        /** @var \Closure(ParsedInputInterface, Console): int $handler */
        $handler = $closure;
        $builder->handler($handler);
    }

    /**
     * Reflect on a standalone function and register the command declared on it.
     *
     * @throws \ReflectionException
     */
    public function loadFunction(string $functionName): void
    {
        $rf = new \ReflectionFunction($functionName);
        $attrs = $rf->getAttributes(Command::class);

        if ($attrs === []) {
            return;
        }

        /** @var Command $command */
        $command = $attrs[0]->newInstance();

        $builder = $this->resolveBuilder($command->parent, $command->name);
        $builder->description($command->description);
        $this->applyOptionAttributes($rf->getAttributes(CommandOption::class), $builder);
        $this->applyArgumentAttributes($rf->getAttributes(CommandArgument::class), $builder);

        $closure = $rf->getClosure();
        /** @var \Closure(ParsedInputInterface, Console): int $handler */
        $handler = $closure;
        $builder->handler($handler);
    }

    /**
     * Walk the parent path to the correct CommandBuilder, creating intermediate
     * builders if needed, then return the builder for $name at that location.
     *
     * @param list<string> $parent
     */
    private function resolveBuilder(array $parent, string $name): CommandBuilder
    {
        if ($parent === []) {
            return $this->router->command($name);
        }

        $current = $this->router->command($parent[0]);

        foreach (\array_slice($parent, 1) as $segment) {
            $current = $current->command($segment);
        }

        return $current->command($name);
    }

    /**
     * @param class-string $className
     *
     * @throws \ReflectionException
     */
    private function resolveInstance(string $className): object
    {
        if ($this->factory instanceof \Closure) {
            return ($this->factory)($className);
        }

        $rc = new \ReflectionClass($className);
        return $rc->newInstance();
    }

    /**
     * @param list<\ReflectionAttribute<CommandOption>> $attrs
     */
    private function applyOptionAttributes(array $attrs, CommandBuilder $builder): void
    {
        foreach ($attrs as $attrRef) {
            /** @var CommandOption $opt */
            $opt = $attrRef->newInstance();
            $ob = $builder->option($opt->long, $opt->short);

            if ($opt->type === OptionType::Flag) {
                $ob->flag();
            } else {
                $ob->value()->repeat($opt->repeat);

                if ($opt->default !== null) {
                    $ob->default($opt->default);
                }

                if ($opt->required) {
                    $ob->required();
                }
            }

            if ($opt->scope === OptionScope::Global) {
                $ob->global();
            }

            if ($opt->description !== '') {
                $ob->description($opt->description);
            }
        }
    }

    /**
     * @param list<\ReflectionAttribute<CommandArgument>> $attrs
     */
    private function applyArgumentAttributes(array $attrs, CommandBuilder $builder): void
    {
        foreach ($attrs as $attrRef) {
            /** @var CommandArgument $arg */
            $arg = $attrRef->newInstance();
            $ab = $builder->argument($arg->name);

            match ($arg->mode) {
                ArgumentMode::Required => $ab->required(),
                ArgumentMode::Optional => $ab->optional(),
                ArgumentMode::Variadic => $ab->variadic(),
            };

            if ($arg->enumClass !== null) {
                $ab->enum($arg->enumClass);
            }

            if ($arg->description !== '') {
                $ab->description($arg->description);
            }
        }
    }

    /**
     * Token-parse a PHP file to extract fully-qualified class names and function names
     * without executing the file.
     *
     * @return array{list<class-string>, list<string>}
     */
    private function extractNames(string $filePath): array
    {
        $source = \file_get_contents($filePath);

        if ($source === false) {
            return [[], []];
        }

        $tokens = \token_get_all($source);
        /** @var list<class-string> $classNames */
        $classNames = [];
        /** @var list<string> $functionNames */
        $functionNames = [];
        $namespace = '';
        $count = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!\is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === \T_NAMESPACE) {
                $namespace = $this->readQualifiedName($tokens, $i + 1, $count);
                continue;
            }

            if (\in_array($tokens[$i][0], [\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM], true)) {
                $name = $this->readSimpleName($tokens, $i + 1, $count);

                if ($name !== '') {
                    $fqcn = $namespace !== '' ? $namespace . '\\' . $name : $name;
                    /** @var class-string $fqcn */
                    $classNames[] = $fqcn;
                }

                continue;
            }

            if ($tokens[$i][0] === \T_FUNCTION) {
                // Check that this is a named top-level function, not a closure or method.
                $name = $this->readSimpleName($tokens, $i + 1, $count);

                if ($name !== '') {
                    $fqn = $namespace !== '' ? $namespace . '\\' . $name : $name;
                    $functionNames[] = $fqn;
                }
            }
        }

        return [$classNames, $functionNames];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readQualifiedName(array $tokens, int $start, int $count): string
    {
        $name = '';

        for ($i = $start; $i < $count; $i++) {
            if (!\is_array($tokens[$i])) {
                if ($tokens[$i] === ';' || $tokens[$i] === '{') {
                    break;
                }

                continue;
            }

            if (\in_array($tokens[$i][0], [\T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_STRING], true)) {
                $name .= $tokens[$i][1];
            } elseif ($tokens[$i][0] === \T_NS_SEPARATOR) {
                $name .= '\\';
            } elseif ($tokens[$i][0] === \T_WHITESPACE && $name !== '') {
                break;
            }
        }

        return \trim($name, '\\');
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readSimpleName(array $tokens, int $start, int $count): string
    {
        for ($i = $start; $i < $count; $i++) {
            if (!\is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === \T_WHITESPACE) {
                continue;
            }

            if ($tokens[$i][0] === \T_STRING) {
                return $tokens[$i][1];
            }

            break;
        }

        return '';
    }
}
