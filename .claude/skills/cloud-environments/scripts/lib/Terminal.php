<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * Where `secrets` reads values from and writes prompts to. On a terminal each value is read
 * with echo off.
 */
class Terminal
{
    /**
     * @param  resource  $in
     * @param  resource  $out
     */
    public function __construct(private $in, private $out) {}

    public function isInteractive(): bool
    {
        return stream_isatty($this->in);
    }

    public function say(string $line): void
    {
        fwrite($this->out, $line."\n");
    }

    /**
     * Reads one line. On a terminal the typed characters are not shown.
     */
    public function readValue(string $prompt): ?string
    {
        $hidden = $this->isInteractive();

        if ($hidden) {
            fwrite($this->out, $prompt);
            shell_exec('stty -echo < /dev/tty');

            // Ctrl-C at a hidden prompt must not leave the terminal without echo.
            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGINT, static function (): never {
                    shell_exec('stty echo < /dev/tty');
                    exit(130);
                });
            }
        }

        // Wait in short select calls rather than one blocking read, so a Ctrl-C is handled at
        // once instead of after the next Enter.
        while ($hidden) {
            $read = [$this->in];
            $write = $except = null;

            if (@stream_select($read, $write, $except, 0, 200000) !== 0) {
                break;
            }
        }

        $line = fgets($this->in);

        if ($hidden) {
            shell_exec('stty echo < /dev/tty');
            fwrite($this->out, "\n");
        }

        return $line === false ? null : rtrim($line, "\r\n");
    }
}
