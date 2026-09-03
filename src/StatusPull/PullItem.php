<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\StatusPull;

final readonly class PullItem
{
    public function __construct(
        public string $publicId,
        public string $rawStatus,
        public bool $inProgress,
        public bool $unknown,
        public ?string $status,
        public ?string $reason,
        public ?string $updatedAt,
    ) {}

    public function isTerminal(): bool
    {
        return $this->status !== null;
    }
}
