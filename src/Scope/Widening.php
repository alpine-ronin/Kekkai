<?php

declare(strict_types=1);

namespace Kekkai\Scope;

final readonly class Widening
{
    /**
     * @param list<string> $entries
     * @param string $reason
     * @param string $by
     * @param string $at
     */
    public function __construct(
        public array $entries,
        public string $reason,
        public string $by,
        public string $at,
    ) {
    }

    /**
     * @return array{entries: list<string>, reason: string, by: string, at: string}
     */
    public function toArray(): array
    {
        return ['entries' => $this->entries, 'reason' => $this->reason, 'by' => $this->by, 'at' => $this->at];
    }
}
