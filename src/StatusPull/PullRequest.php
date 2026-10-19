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
        public string $bodyEncoding = PullSpec::ENCODING_JSON,
    ) {}

    /**
     * Guzzle request options for the body: JSON or form fields, nothing when there is no body.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyOptions(): array
    {
        if ($this->body === null) {
            return [];
        }

        return $this->bodyEncoding === PullSpec::ENCODING_FORM
            ? ['form_params' => $this->body]
            : ['json' => $this->body];
    }
}
