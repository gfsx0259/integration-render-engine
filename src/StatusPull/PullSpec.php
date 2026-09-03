<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\StatusPull;

/**
 * JSON pull_spec on connection_adapters: how to request and parse partner statuses.
 *
 * @phpstan-type PageSpec array{start?: int, param?: string, in?: string}
 * @phpstan-type ApprovedWhen array{path: string, eq: mixed}
 */
final readonly class PullSpec
{
    public const string METHOD_GET = 'GET';
    public const string METHOD_POST = 'POST';

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body
     * @param array<string, array{status: string, reason?: string}|null> $statusMap
     * @param PageSpec|null $page
     * @param ApprovedWhen|null $alsoApprovedWhen
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers,
        public ?array $body,
        public int $lookbackDays,
        public string $items,
        public string $id,
        public string $status,
        public ?string $updated,
        public array $statusMap,
        public ?array $alsoApprovedWhen,
        public ?array $page,
    ) {}

    public function toArray(): array
    {
        $out = [
            'method' => $this->method,
            'path' => $this->path,
            'lookback_days' => $this->lookbackDays,
            'items' => $this->items,
            'id' => $this->id,
            'status' => $this->status,
            'status_map' => $this->statusMap,
        ];

        if ($this->headers !== []) {
            $out['headers'] = $this->headers;
        }
        if ($this->body !== null) {
            $out['body'] = $this->body;
        }
        if ($this->updated !== null) {
            $out['updated'] = $this->updated;
        }
        if ($this->alsoApprovedWhen !== null) {
            $out['also_approved_when'] = $this->alsoApprovedWhen;
        }
        if ($this->page !== null) {
            $out['page'] = $this->page;
        }

        return $out;
    }

    public function pageStart(): int
    {
        return (int) ($this->page['start'] ?? 1);
    }

    public function pageParam(): string
    {
        return (string) ($this->page['param'] ?? 'page');
    }

    public function pageInBody(): bool
    {
        return ($this->page['in'] ?? 'body') !== 'query';
    }
}
