<?php

declare(strict_types=1);

namespace Kekkai;

final class Path
{
    /**
     * @param string $root
     * @param string $path
     */
    public static function relative(string $root, string $path): ?string
    {
        $root = self::normalize($root);
        $path = str_replace('\\', '/', $path);
        $absolute = self::isAbsolute($path) ? self::normalize($path) : self::normalize($root . '/' . $path);

        if ($absolute === $root) {
            return '';
        }

        $prefix = rtrim($root, '/') . '/';

        return str_starts_with($absolute, $prefix) ? substr($absolute, \strlen($prefix)) : null;
    }

    /**
     * @param string $path
     */
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $path) === 1;
    }

    /**
     * @param string $path
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $drive = '';
        if (preg_match('#^([A-Za-z]:)/#', $path, $match) === 1) {
            $drive = $match[1];
            $path = substr($path, 2);
        }

        $absolute = str_starts_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments !== [] && end($segments) !== '..') {
                    array_pop($segments);
                } elseif (!$absolute) {
                    $segments[] = '..';
                }

                continue;
            }
            $segments[] = $segment;
        }

        return $drive . ($absolute ? '/' : '') . implode('/', $segments);
    }
}
