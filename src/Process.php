<?php

declare(strict_types=1);

namespace Kekkai;

final readonly class Process
{
    /**
     * @param int $exitCode
     * @param string $output
     * @param bool $timedOut
     */
    public function __construct(
        public int $exitCode,
        public string $output,
        public bool $timedOut = false,
    ) {
    }

    /**
     * @param list<string>|string $command
     * @param string $cwd
     * @param int $timeout
     * @param array<string, string> $env
     */
    public static function run(array|string $command, string $cwd, int $timeout = 60, array $env = []): self
    {
        $environment = array_merge(getenv(), $env);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $environment);
        if (!\is_resource($process)) {
            return new self(127, 'Could not start the process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $deadline = microtime(true) + $timeout;
        $timedOut = false;
        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            if (@stream_select($read, $write, $except, 0, 200_000) > 0) {
                foreach ($read as $pipe) {
                    $output .= (string) fread($pipe, 65536);
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $output .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return new self($status['exitcode'], $output);
            }

            if (microtime(true) > $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return new self(124, $output, $timedOut);
    }
}
