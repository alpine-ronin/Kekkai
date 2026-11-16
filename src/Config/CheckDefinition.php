<?php

declare(strict_types=1);

namespace Kekkai\Config;

final readonly class CheckDefinition
{
    /**
     * @param string $name
     * @param string $run
     * @param int $timeout
     */
    public function __construct(
        public string $name,
        public string $run,
        public int $timeout = 300,
    ) {
    }
}
