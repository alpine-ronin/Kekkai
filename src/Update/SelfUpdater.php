<?php

declare(strict_types=1);

namespace Kekkai\Update;

use Kekkai\Process;

final readonly class SelfUpdater
{
    private const PREFIX = '.kekkai-update-';

    /**
     * @param Releases $releases
     * @param bool $verifyAttestation
     */
    public function __construct(
        private Releases $releases,
        private bool $verifyAttestation = true,
    ) {
    }

    /**
     * @param string $target
     * @param string $asset
     * @param string $channel
     *
     * @return array{file: string, version: string, attested: bool|null}
     */
    public function download(string $target, string $asset, string $channel): array
    {
        $directory = \dirname($target);
        if (!is_writable($directory)) {
            throw new UpdateFailed(sprintf('Cannot write to %s, where %s is: run kekkai self-update as the user who installed it.', $directory, basename($target)));
        }

        foreach (glob($directory . '/' . self::PREFIX . '*') ?: [] as $leftover) {
            @unlink($leftover);
        }

        $version = $this->releases->version($channel);
        $expected = $this->releases->checksum($channel, $asset);
        $file = tempnam($directory, self::PREFIX);
        if ($file === false) {
            throw new UpdateFailed(sprintf('Could not create a temporary file in %s.', $directory));
        }

        try {
            $this->releases->download($channel, $asset, $file);
            if (!hash_equals($expected, (string) hash_file('sha256', $file))) {
                throw new UpdateFailed(sprintf('%s of %s does not match its checksum: not installed.', $asset, $channel));
            }
            $attested = $this->verifyAttestation ? self::attest($file) : null;
            if (!chmod($file, 0o755)) {
                throw new UpdateFailed(sprintf('Could not make %s executable.', $file));
            }
        } catch (UpdateFailed $exception) {
            @unlink($file);

            throw $exception;
        }

        return ['file' => $file, 'version' => $version, 'attested' => $attested];
    }

    /**
     * @param string $file
     * @param string $target
     */
    public static function replace(string $file, string $target): never
    {
        if (\function_exists('pcntl_exec') && is_executable('/bin/mv')) {
            pcntl_exec('/bin/mv', ['-f', $file, $target]);
        }

        if (!rename($file, $target)) {
            @unlink($file);
            fwrite(\STDERR, sprintf("Could not put %s in place of %s.\n", $file, $target));
            exit(1);
        }

        exit(0);
    }

    /**
     * @param string $file
     */
    private static function attest(string $file): ?bool
    {
        $gh = Process::run(['sh', '-c', 'command -v gh'], sys_get_temp_dir(), 10);
        if ($gh->exitCode !== 0 || Process::run(['gh', 'auth', 'status', '--hostname', 'github.com'], sys_get_temp_dir(), 20)->exitCode !== 0) {
            return null;
        }

        $verify = Process::run(['gh', 'attestation', 'verify', $file, '--repo', 'alpine-ronin/kekkai'], sys_get_temp_dir(), 120);
        if ($verify->exitCode !== 0) {
            throw new UpdateFailed("The download has no attestation that the release workflow of alpine-ronin/kekkai built it: not installed.\n" . trim($verify->output));
        }

        return true;
    }
}
