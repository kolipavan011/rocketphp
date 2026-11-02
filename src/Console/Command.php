<?php

declare(strict_types=1);

namespace Rocket\Console;

use Rocket\Console\Contracts\CommandInterface;
use Rocket\Foundation\Application;

/**
 * Class Command
 *
 * Base class for all Rocket CLI commands.
 */
abstract class Command implements CommandInterface
{
    protected string $name = '';
    protected string $description = '';
    protected Application $app;
    protected array $arguments = [];
    protected array $options = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function execute(array $arguments, array $options): int
    {
        $this->arguments = $arguments;
        $this->options = $options;

        return $this->handle($arguments, $options);
    }

    abstract public function handle(array $arguments, array $options): int;

    public function argument(int|string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function line(string $message): void
    {
        echo $message . PHP_EOL;
    }

    public function info(string $message): void
    {
        echo "\033[34mℹ {$message}\033[0m" . PHP_EOL;
    }

    public function success(string $message): void
    {
        echo "\033[32m✔ {$message}\033[0m" . PHP_EOL;
    }

    public function warn(string $message): void
    {
        echo "\033[33m⚠ {$message}\033[0m" . PHP_EOL;
    }

    public function error(string $message): void
    {
        echo "\033[31m✖ {$message}\033[0m" . PHP_EOL;
    }

    /**
     * Render an ASCII table in the console.
     */
    public function table(array $headers, array $rows): void
    {
        if (empty($headers)) {
            return;
        }

        $colWidths = [];
        foreach ($headers as $i => $header) {
            $colWidths[$i] = mb_strlen((string) $header);
        }

        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $val) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, mb_strlen((string) $val));
            }
        }

        $separator = '+';
        foreach ($colWidths as $w) {
            $separator .= str_repeat('-', $w + 2) . '+';
        }

        echo $separator . PHP_EOL;

        // Headers
        echo '|';
        foreach ($headers as $i => $header) {
            echo ' ' . str_pad((string) $header, $colWidths[$i]) . ' |';
        }
        echo PHP_EOL;

        echo $separator . PHP_EOL;

        // Rows
        foreach ($rows as $row) {
            echo '|';
            foreach (array_values($row) as $i => $val) {
                echo ' ' . str_pad((string) $val, $colWidths[$i]) . ' |';
            }
            echo PHP_EOL;
        }

        echo $separator . PHP_EOL;
    }
}

