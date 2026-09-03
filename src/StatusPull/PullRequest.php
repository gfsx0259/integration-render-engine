<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\StatusPull;

final readonly class PullRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers,
        public ?array $body,
    ) {}
}
