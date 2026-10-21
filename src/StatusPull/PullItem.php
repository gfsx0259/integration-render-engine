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
        public bool $deposit = false,
    ) {}

    public function isTerminal(): bool
    {
        return $this->status !== null;
    }

    public function isCallback(): bool
    {
        return $this->status === 'approved' || $this->status === 'rejected';
    }

    /**
     * Conversion rule on top of mapping. Deposit forces approved regardless of
     * call status. On a deposit-signal adapter, mapped approved is not a conversion.
     */
    public function forPublish(bool $depositBySignal): self
    {
        if ($this->deposit) {
            return new self(
                $this->publicId,
                $this->rawStatus,
                inProgress: false,
                unknown: false,
                status: 'approved',
                reason: null,
                updatedAt: $this->updatedAt,
                deposit: true,
            );
        }

        if ($depositBySignal && $this->status === 'approved') {
            return new self(
                $this->publicId,
                $this->rawStatus,
                inProgress: true,
                unknown: false,
                status: null,
                reason: null,
                updatedAt: $this->updatedAt,
                deposit: false,
            );
        }

        return $this;
    }
}
