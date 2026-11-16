<?php

declare(strict_types=1);

namespace Kekkai;

final class Glob
{
    private readonly string $regex;

    /**
     * @param string $pattern
     */
    public function __construct(public readonly string $pattern)
    {
        $normalized = ltrim(str_replace('\\', '/', $pattern), '/');
        if (str_starts_with($normalized, './')) {
            $normalized = substr($normalized, 2);
        }

        if (!str_contains($normalized, '*') && !str_contains($normalized, '?')) {
            $this->regex = '#^' . preg_quote(rtrim($normalized, '/'), '#') . '(?:/.*)?$#';

            return;
        }

        if (str_ends_with($normalized, '/')) {
            $normalized .= '**';
        }

        $this->regex = '#^' . self::translate($normalized) . '$#';
    }

    /**
     * @param string $path
     */
    public function matches(string $path): bool
    {
        return preg_match($this->regex, ltrim(str_replace('\\', '/', $path), '/')) === 1;
    }

    public function coversEverything(): bool
    {
        return $this->matches('a') && $this->matches('a/b/c.php') && $this->matches('.hidden/x');
    }

    /**
     * @param string $pattern
     */
    private static function translate(string $pattern): string
    {
        $regex = '';
        $length = \strlen($pattern);
        for ($i = 0; $i < $length; ++$i) {
            $char = $pattern[$i];
            if ($char === '*' && ($pattern[$i + 1] ?? '') === '*') {
                $slash = ($pattern[$i + 2] ?? '') === '/';
                $regex .= $slash ? '(?:.*/)?' : '.*';
                $i += $slash ? 2 : 1;
            } elseif ($char === '*') {
                $regex .= '[^/]*';
            } elseif ($char === '?') {
                $regex .= '[^/]';
            } else {
                $regex .= preg_quote($char, '#');
            }
        }

        return $regex;
    }
}
