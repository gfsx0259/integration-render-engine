<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\StatusPull;

/**
 * JSON pull_spec on connection_adapters: how to request and parse partner statuses.
 *
 * @phpstan-type PageSpec array{start: int, param: string, in: string, step?: int}
 * @phpstan-type CursorSpec array{from_response: string, apply_to: string, format: string}
 * @phpstan-type PaginationSpec array{page?: PageSpec, cursor?: CursorSpec}
 * @phpstan-type ApprovedWhen array{path: string, eq?: mixed, not_empty?: true}
 */
final readonly class PullSpec
{
    public const string METHOD_GET = 'GET';
    public const string METHOD_POST = 'POST';
    public const string DEFAULT_DATE_FORMAT = 'Y-m-d';
    public const string DATE_UNIX = 'unix';
    public const string DATE_UNIX_MS = 'unix_ms';
    public const string ENCODING_JSON = 'json';
    public const string ENCODING_FORM = 'form';
    public const string ENCODING_QUERY = 'query';
    public const array ENCODINGS = [self::ENCODING_JSON, self::ENCODING_FORM, self::ENCODING_QUERY];
    public const int DEFAULT_DEPOSIT_LOOKBACK_DAYS = 30;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body
     * @param array<string, array{status: string, reason?: string}|null> $statusMap
     * @param PaginationSpec|null $pagination
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
        public ?array $pagination,
        public ?string $dateFormat = null,
        public string $bodyEncoding = self::ENCODING_JSON,
        public ?PullSpec $deposits = null,
        public bool $depositList = false,
    ) {}

    public function toArray(): array
    {
        $out = [
            'method' => $this->method,
            'path' => $this->path,
            'lookback_days' => $this->lookbackDays,
            'items' => $this->items,
            'id' => $this->id,
        ];

        if (!$this->depositList) {
            $out['status'] = $this->status;
            $out['status_map'] = $this->statusMap;
        } elseif ($this->status !== '') {
            $out['status'] = $this->status;
        }

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
        if ($this->pagination !== null) {
            $out['pagination'] = $this->pagination;
        }
        if ($this->dateFormat !== null) {
            $out['date_format'] = $this->dateFormat;
        }
        if ($this->bodyEncoding !== self::ENCODING_JSON) {
            $out['body_encoding'] = $this->bodyEncoding;
        }
        if ($this->deposits !== null) {
            $out['deposits'] = $this->deposits->toArray();
        }

        return $out;
    }

    /**
     * @return PageSpec|null
     */
    public function page(): ?array
    {
        return is_array($this->pagination['page'] ?? null) ? $this->pagination['page'] : null;
    }

    /**
     * @return CursorSpec|null
     */
    public function cursor(): ?array
    {
        return is_array($this->pagination['cursor'] ?? null) ? $this->pagination['cursor'] : null;
    }

    public function pageStart(): int
    {
        return (int) ($this->page()['start'] ?? 1);
    }

    public function pageStep(): int
    {
        return max(1, (int) ($this->page()['step'] ?? 1));
    }

    public function decidesDepositBySignal(): bool
    {
        return $this->alsoApprovedWhen !== null || $this->deposits !== null;
    }

    public function pageParam(): string
    {
        return (string) ($this->page()['param'] ?? 'page');
    }

    public function pageInBody(): bool
    {
        return ($this->page()['in'] ?? 'body') !== 'query';
    }

    public function pollDateFormat(): string
    {
        return $this->dateFormat ?? $this->cursor()['format'] ?? self::DEFAULT_DATE_FORMAT;
    }

    public function formatPollDate(\DateTimeImmutable $at): string
    {
        return match ($this->pollDateFormat()) {
            self::DATE_UNIX => (string) $at->getTimestamp(),
            self::DATE_UNIX_MS => (string) ($at->getTimestamp() * 1000),
            default => $at->format($this->pollDateFormat()),
        };
    }
}
