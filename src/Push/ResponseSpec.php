<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Push;

/**
 * @phpstan-type OkRule array{path: string, eq: mixed}
 */
final readonly class ResponseSpec
{
    /**
     * @param OkRule|null $ok
     */
    public function __construct(
        public ?array $ok = null,
        public ?string $externalId = null,
        public ?string $autologin = null,
        public ?string $error = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'ok' => $this->ok,
            'external_id' => $this->externalId,
            'autologin' => $this->autologin,
            'error' => $this->error,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
