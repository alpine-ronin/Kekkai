<?php

declare(strict_types=1);

namespace Kekkai;

use Kekkai\Config\Config;
use Kekkai\Gate\Gate;
use Kekkai\Scope\Scope;
use Kekkai\Scope\ScopeStore;

final readonly class Project
{
    /**
     * @param string $root
     * @param Config $config
     * @param ScopeStore $store
     */
    private function __construct(
        public string $root,
        public Config $config,
        public ScopeStore $store,
    ) {
    }

    /**
     * @param string|null $root
     */
    public static function at(?string $root = null): self
    {
        $candidate = $root ?? (getenv('CLAUDE_PROJECT_DIR') ?: (getcwd() ?: '.'));
        $real = realpath($candidate);
        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException(sprintf('Project directory not found: %s', $candidate));
        }

        return new self($real, Config::load($real), new ScopeStore($real));
    }

    public function scope(): ?Scope
    {
        return $this->store->load();
    }

    public function gate(): Gate
    {
        return new Gate($this->root, $this->config, $this->scope());
    }
}
