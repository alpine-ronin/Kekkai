<?php

declare(strict_types=1);

namespace Kekkai\Tests\Support;

final class Json
{
    /**
     * @param mixed $data
     * @param int|string ...$keys
     */
    public static function path(mixed $data, int|string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }

    /**
     * @param string $json
     * @param int|string ...$keys
     */
    public static function decode(string $json, int|string ...$keys): mixed
    {
        return self::path(json_decode($json, true), ...$keys);
    }
}
