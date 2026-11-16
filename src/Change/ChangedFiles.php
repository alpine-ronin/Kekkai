<?php

declare(strict_types=1);

namespace Kekkai\Change;

use Kekkai\Process;

final readonly class ChangedFiles
{
    /**
     * @param string $root
     */
    public function __construct(private string $root)
    {
    }

    /**
     * @param string $base
     *
     * @return list<string>
     */
    public function since(string $base = 'HEAD'): array
    {
        $inside = Process::run(['git', 'rev-parse', '--is-inside-work-tree'], $this->root);
        if ($inside->exitCode !== 0) {
            throw new \RuntimeException(sprintf('%s is not a git repository: Kekkai reads the changes from git.', $this->root));
        }

        $hasCommit = Process::run(['git', 'rev-parse', '--verify', '--quiet', $base . '^{commit}'], $this->root)->exitCode === 0;
        $tracked = $hasCommit
            ? Process::run(['git', 'diff', '--name-only', '-z', '--no-renames', $base, '--'], $this->root)
            : Process::run(['git', 'diff', '--name-only', '-z', '--cached', '--'], $this->root);
        if ($tracked->exitCode !== 0) {
            throw new \RuntimeException(sprintf('git diff %s failed: %s', $base, trim($tracked->output)));
        }

        $untracked = Process::run(['git', 'ls-files', '--others', '--exclude-standard', '-z'], $this->root);

        $files = array_merge(explode("\0", $tracked->output), explode("\0", $untracked->output));
        $files = array_values(array_unique(array_filter($files, static fn (string $file): bool => $file !== '')));
        sort($files);

        return $files;
    }
}
