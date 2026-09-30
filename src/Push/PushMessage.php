<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Push;

final readonly class PushMessage
{
    private const string FORM_CONTENT_TYPE = 'application/x-www-form-urlencoded';

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        public array $payload,
        public array $headers,
        public BodyEncoding $encoding,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function transformationParams(): array
    {
        $headers = $this->encoding === BodyEncoding::Form
            ? array_merge($this->headers, ['content-type' => self::FORM_CONTENT_TYPE])
            : $this->headers;

        return array_filter(
            [
                'rawPayload' => match ($this->encoding) {
                    BodyEncoding::Form => http_build_query($this->payload),
                    BodyEncoding::Query => '',
                    BodyEncoding::Json => null,
                },
                'headers' => $headers,
                'query' => $this->encoding === BodyEncoding::Query ? http_build_query($this->payload) : null,
            ],
            static fn (mixed $value): bool => $value !== null && $value !== [],
        );
    }
}
