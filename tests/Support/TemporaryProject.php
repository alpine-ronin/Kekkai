<?php

declare(strict_types=1);

namespace Kekkai\Tests\Support;

use Kekkai\Process;

final class TemporaryProject
{
    public readonly string $root;

    /**
     * @param bool $git
     */
    public function __construct(bool $git = true)
    {
        $root = sys_get_temp_dir() . '/kekkai-test-' . bin2hex(random_bytes(6));
        mkdir($root, 0o777, true);
        $this->root = (string) realpath($root);

        if ($git) {
            $this->git('init', '-q', '-b', 'main');
            $this->git('config', 'user.email', 'test@example.com');
            $this->git('config', 'user.name', 'Test');
            $this->git('config', 'commit.gpgsign', 'false');
        }
    }

    public function __destruct()
    {
        Process::run(['rm', '-rf', $this->root], sys_get_temp_dir());
    }

    /**
     * @param string $path
     * @param string $content
     */
    public function write(string $path, string $content = "<?php\n"): self
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o777, true);
        }
        file_put_contents($file, $content);

        return $this;
    }

    /**
     * @param string $path
     */
    public function delete(string $path): self
    {
        unlink($this->root . '/' . $path);

        return $this;
    }

    /**
     * @param string $message
     */
    public function commit(string $message = 'commit'): self
    {
        $this->git('add', '-A');
        $this->git('commit', '-q', '--allow-empty', '-m', $message);

        return $this;
    }

    /**
     * @param string ...$arguments
     */
    public function git(string ...$arguments): string
    {
        $process = Process::run(['git', ...array_values($arguments)], $this->root);
        if ($process->exitCode !== 0) {
            throw new \RuntimeException(sprintf('git %s failed: %s', implode(' ', $arguments), $process->output));
        }

        return $process->output;
    }

    /**
     * @param string $path
     */
    public function read(string $path): string
    {
        return (string) file_get_contents($this->root . '/' . $path);
    }
}
