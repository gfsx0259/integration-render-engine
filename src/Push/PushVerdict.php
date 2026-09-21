<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Push;

final readonly class PushVerdict
{
    public const string ACCEPTED = 'accepted';
    public const string REJECTED = 'rejected';
    public const string ERROR = 'error';

    public function __construct(
        public string $outcome,
        public int $code,
        public ?string $externalId = null,
        public ?string $autologin = null,
        public ?string $error = null,
    ) {}

    public function accepted(): bool
    {
        return $this->outcome === self::ACCEPTED;
    }

    public function rejected(): bool
    {
        return $this->outcome === self::REJECTED;
    }
}
