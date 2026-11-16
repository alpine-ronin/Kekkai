<?php

declare(strict_types=1);

namespace Kekkai\Update;

final readonly class Releases
{
    public const DEFAULT_URL = 'https://github.com/alpine-ronin/kekkai/releases';

    private string $base;

    /**
     * @param string|null $base
     */
    public function __construct(?string $base = null)
    {
        $env = getenv('KEKKAI_RELEASES_URL');
        $this->base = rtrim($base ?? (\is_string($env) && $env !== '' ? $env : self::DEFAULT_URL), '/');
    }

    /**
     * @param string $channel
     */
    public function version(string $channel = 'latest'): string
    {
        $version = trim($this->fetch($this->url($channel, 'VERSION')));
        if ($channel === 'edge') {
            return $version === '' ? 'dev-main' : $version;
        }
        if (preg_match('/^v?(\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?)$/', $version, $match) !== 1) {
            throw new UpdateFailed(sprintf('%s does not hold a version: "%s".', $this->url($channel, 'VERSION'), $version));
        }

        return $match[1];
    }

    /**
     * @param string $channel
     * @param string $file
     * @param string $path
     */
    public function download(string $channel, string $file, string $path): void
    {
        $content = $this->fetch($this->url($channel, $file));
        if (file_put_contents($path, $content) !== \strlen($content)) {
            throw new UpdateFailed(sprintf('Could not write %s.', $path));
        }
    }

    /**
     * @param string $channel
     * @param string $file
     */
    public function checksum(string $channel, string $file): string
    {
        $sums = $this->fetch($this->url($channel, 'SHA256SUMS'));
        foreach (preg_split('/\R/', $sums) ?: [] as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if ($fields !== false && \count($fields) === 2 && ltrim($fields[1], '*') === $file && preg_match('/^[0-9a-f]{64}$/i', $fields[0]) === 1) {
                return strtolower($fields[0]);
            }
        }

        throw new UpdateFailed(sprintf('The SHA256SUMS of %s lists no %s.', $channel, $file));
    }

    /**
     * @param string $channel
     * @param string $file
     */
    public function url(string $channel, string $file): string
    {
        return match (true) {
            $channel === 'latest' => sprintf('%s/latest/download/%s', $this->base, $file),
            $channel === 'edge' => sprintf('%s/download/edge/%s', $this->base, $file),
            default => sprintf('%s/download/v%s/%s', $this->base, ltrim($channel, 'v'), $file),
        };
    }

    /**
     * @param string $url
     */
    private function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => ['follow_location' => 1, 'max_redirects' => 10, 'timeout' => 60, 'ignore_errors' => false, 'header' => "User-Agent: kekkai-self-update\r\n"],
        ]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $error = (string) (error_get_last()['message'] ?? 'unknown error');
            $reason = str_contains($error, ' 404 ') ? 'not found, is there such a release?' : (preg_replace('/^.*?: /', '', $error) ?? $error);

            throw new UpdateFailed(sprintf('Could not download %s: %s', $url, $reason));
        }

        return $content;
    }
}
