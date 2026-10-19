<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\StatusPull;

use DomainException;
use RuntimeException;

final class PullSpecParser
{
    public function __construct(
        private readonly SaleStatusMapper $saleStatuses = new SaleStatusMapper(),
    ) {}

    /**
     * Save path: validate user JSON and build a spec. Empty / null disables pull.
     *
     * @param array<string, mixed>|null $raw
     */
    public function hydrateAndValidate(?array $raw): ?PullSpec
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        return $this->validated($raw, false);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function validated(array $raw, bool $depositList): PullSpec
    {
        $field = $depositList ? 'deposits.' : '';

        $method = strtoupper(trim((string) ($raw['method'] ?? '')));
        if ($method !== PullSpec::METHOD_GET && $method !== PullSpec::METHOD_POST) {
            throw new DomainException("pull_spec.{$field}method must be GET or POST");
        }

        $path = trim((string) ($raw['path'] ?? ''));
        if ($path === '' || (!str_starts_with($path, '/') && !str_starts_with($path, '{{static.'))) {
            throw new DomainException("pull_spec.{$field}path must start with / or a {{static.*}} base URL");
        }

        $items = ($raw['items'] ?? null) === '' ? '' : $this->dottedPath($raw['items'] ?? null, $field . 'items');
        $id = $this->dottedPath($raw['id'] ?? null, $field . 'id');
        $status = $depositList && ($raw['status'] ?? '') === ''
            ? ''
            : $this->dottedPath($raw['status'] ?? null, $field . 'status');
        $updated = !$depositList && isset($raw['updated']) && $raw['updated'] !== '' && $raw['updated'] !== null
            ? $this->dottedPath($raw['updated'], 'updated')
            : null;

        $defaultLookback = $depositList ? PullSpec::DEFAULT_DEPOSIT_LOOKBACK_DAYS : 7;
        $lookback = (int) ($raw['lookback_days'] ?? $defaultLookback);
        if ($lookback < 1 || $lookback > 90) {
            throw new DomainException("pull_spec.{$field}lookback_days must be 1–90");
        }

        $dateFormat = null;
        if (isset($raw['date_format']) && $raw['date_format'] !== '') {
            $dateFormat = trim((string) $raw['date_format']);
            if ($dateFormat === '' || strlen($dateFormat) > 64) {
                throw new DomainException("pull_spec.{$field}date_format must be unix, unix_ms or a PHP date format");
            }
        }

        $bodyEncoding = (string) ($raw['body_encoding'] ?? PullSpec::ENCODING_JSON);
        if (!in_array($bodyEncoding, PullSpec::ENCODINGS, true)) {
            throw new DomainException("pull_spec.{$field}body_encoding must be json, form or query");
        }

        $deposits = null;
        if (!$depositList && isset($raw['deposits']) && $raw['deposits'] !== []) {
            if (!is_array($raw['deposits'])) {
                throw new DomainException('pull_spec.deposits must be an object');
            }
            $deposits = $this->validated($raw['deposits'], true);
        }

        $headers = [];
        if (isset($raw['headers']) && is_array($raw['headers'])) {
            foreach ($raw['headers'] as $name => $value) {
                if (is_string($name) && $name !== '' && is_scalar($value)) {
                    $headers[$name] = (string) $value;
                }
            }
        }

        $body = null;
        if (isset($raw['body']) && is_array($raw['body'])) {
            $body = $raw['body'];
        }

        return new PullSpec(
            $method,
            $path,
            $headers,
            $body,
            $lookback,
            $items,
            $id,
            $status,
            $updated,
            $depositList ? [] : $this->statusMap($raw['status_map'] ?? null),
            $this->alsoApprovedWhen($raw['also_approved_when'] ?? null),
            $this->pagination($raw),
            $dateFormat,
            $bodyEncoding,
            $deposits,
            $depositList,
        );
    }

    /**
     * Stored pull_spec after a successful save. Shape is {@see PullSpec::toArray()}.
     *
     * @param array<string, mixed> $raw
     */
    public function hydrate(array $raw): PullSpec
    {
        return $this->stored($raw, false);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function stored(array $raw, bool $depositList): PullSpec
    {
        $headers = [];
        if (is_array($raw['headers'] ?? null)) {
            foreach ($raw['headers'] as $name => $value) {
                if (is_string($name) && is_scalar($value)) {
                    $headers[$name] = (string) $value;
                }
            }
        }

        return new PullSpec(
            (string) $raw['method'],
            (string) $raw['path'],
            $headers,
            is_array($raw['body'] ?? null) ? $raw['body'] : null,
            (int) $raw['lookback_days'],
            (string) $raw['items'],
            (string) $raw['id'],
            (string) ($raw['status'] ?? ''),
            isset($raw['updated']) && $raw['updated'] !== '' ? (string) $raw['updated'] : null,
            is_array($raw['status_map'] ?? null) ? $raw['status_map'] : [],
            is_array($raw['also_approved_when'] ?? null) ? $raw['also_approved_when'] : null,
            $this->storedPagination($raw),
            isset($raw['date_format']) && $raw['date_format'] !== '' ? (string) $raw['date_format'] : null,
            (string) ($raw['body_encoding'] ?? PullSpec::ENCODING_JSON),
            !$depositList && is_array($raw['deposits'] ?? null) ? $this->stored($raw['deposits'], true) : null,
            $depositList,
        );
    }

    /**
     * @param array<string, mixed> $response
     * @return list<PullItem>
     */
    public function parse(array $response, PullSpec $spec): array
    {
        if ($this->isAuthFailure($response)) {
            throw new RuntimeException('Partner status pull returned an auth error');
        }

        $items = $this->lookup($response, $spec->items);
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = $this->mapRow($row, $spec);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row, PullSpec $spec): ?PullItem
    {
        $publicId = trim((string) $this->scalar($this->lookup($row, $spec->id)));
        if ($publicId === '') {
            return null;
        }

        $rawStatus = $spec->status === '' ? '' : trim((string) $this->scalar($this->lookup($row, $spec->status)));
        $updated = $spec->updated === null
            ? null
            : $this->scalar($this->lookup($row, $spec->updated));
        $updatedAt = $updated === null || $updated === '' ? null : $updated;

        if ($spec->depositList && !$this->alsoApproved($row, $spec, true)) {
            return null;
        }

        if ($spec->depositList || $this->alsoApproved($row, $spec)) {
            return new PullItem(
                $publicId,
                $rawStatus,
                inProgress: false,
                unknown: false,
                status: 'approved',
                reason: null,
                updatedAt: $updatedAt,
            );
        }

        $overridden = array_key_exists($rawStatus, $spec->statusMap);

        if (!$overridden && $this->saleStatuses->isUnknown($rawStatus)) {
            return new PullItem(
                $publicId,
                $rawStatus,
                inProgress: false,
                unknown: true,
                status: null,
                reason: null,
                updatedAt: $updatedAt,
            );
        }

        $mapped = $overridden ? $spec->statusMap[$rawStatus] : $this->saleStatuses->map($rawStatus);
        if ($mapped !== null && $mapped['status'] === 'approved' && $spec->decidesDepositBySignal()) {
            $mapped = null;
        }

        if ($mapped === null) {
            return new PullItem(
                $publicId,
                $rawStatus,
                inProgress: true,
                unknown: false,
                status: null,
                reason: null,
                updatedAt: $updatedAt,
            );
        }

        $reason = isset($mapped['reason']) && is_string($mapped['reason']) && $mapped['reason'] !== ''
            ? $mapped['reason']
            : null;

        return new PullItem(
            $publicId,
            $rawStatus,
            inProgress: false,
            unknown: false,
            status: $mapped['status'],
            reason: $reason,
            updatedAt: $updatedAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function alsoApproved(array $row, PullSpec $spec, bool $whenUnset = false): bool
    {
        if ($spec->alsoApprovedWhen === null) {
            return $whenUnset;
        }

        $actual = $this->lookup($row, $spec->alsoApprovedWhen['path']);
        if (($spec->alsoApprovedWhen['not_empty'] ?? false) === true) {
            return !$this->isEmptyValue($actual);
        }

        return $this->equals($actual, $spec->alsoApprovedWhen['eq'] ?? true);
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null || $value === false || $value === [] || $value === 0 || $value === 0.0) {
            return true;
        }
        if (!is_scalar($value)) {
            return false;
        }

        $text = strtolower(trim((string) $value));

        return in_array($text, ['', '0', 'null', 'false', 'no'], true) || str_starts_with($text, '0000-00-00');
    }

    private function equals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($expected)) {
            return (bool) $actual === $expected;
        }

        if (is_int($expected) || is_float($expected)) {
            return is_numeric($actual) && (float) $actual == (float) $expected;
        }

        return (string) $this->scalar($actual) === (string) $expected;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isAuthFailure(array $response): bool
    {
        if (isset($response['code']) && (int) $response['code'] === 401) {
            return true;
        }

        return isset($response['status']) && $response['status'] === false;
    }

    private function lookup(mixed $node, string $path): mixed
    {
        if ($path === '') {
            return $node;
        }

        $current = $node;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private function dottedPath(mixed $value, string $field): string
    {
        $path = is_string($value) ? trim($value) : '';
        if ($path === '' || preg_match('/^[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*$/', $path) !== 1) {
            throw new DomainException("pull_spec.{$field} must be a dotted path");
        }

        return $path;
    }

    /**
     * @return array<string, array{status: string, reason?: string}|null>
     */
    private function statusMap(mixed $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        if (!is_array($raw)) {
            throw new DomainException('pull_spec.status_map must be an object');
        }

        $map = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }
            $label = trim($key);
            if ($value === null) {
                $map[$label] = null;
                continue;
            }
            if (!is_array($value) || !isset($value['status']) || !is_string($value['status'])) {
                throw new DomainException("pull_spec.status_map.{$label} must be null or {status, reason?}");
            }
            $status = $value['status'];
            if (!in_array($status, ['approved', 'rejected'], true)) {
                throw new DomainException("pull_spec.status_map.{$label}.status must be approved or rejected");
            }
            $entry = ['status' => $status];
            if (isset($value['reason']) && is_string($value['reason']) && $value['reason'] !== '') {
                if (!in_array($value['reason'], ['unavailable', 'not_interested', 'invalid_data'], true)) {
                    throw new DomainException("pull_spec.status_map.{$label}.reason is unknown");
                }
                $entry['reason'] = $value['reason'];
            }
            $map[$label] = $entry;
        }

        return $map;
    }

    /**
     * @return array{path: string, eq?: mixed, not_empty?: true}|null
     */
    private function alsoApprovedWhen(mixed $raw): ?array
    {
        if ($raw === null || $raw === []) {
            return null;
        }
        if (!is_array($raw) || !isset($raw['path'])) {
            throw new DomainException('pull_spec.also_approved_when needs path and eq or not_empty');
        }

        $path = $this->dottedPath($raw['path'], 'also_approved_when.path');
        if (($raw['not_empty'] ?? false) === true) {
            return ['path' => $path, 'not_empty' => true];
        }

        return ['path' => $path, 'eq' => $raw['eq'] ?? true];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function readCursor(array $response, PullSpec $spec): mixed
    {
        $cursor = $spec->cursor();
        if ($cursor === null) {
            return null;
        }

        return $this->lookup($response, $cursor['from_response']);
    }

    public function formatCursor(mixed $raw, PullSpec $spec): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $format = $spec->pollDateFormat();
        $dt = $this->cursorInstant($raw);
        if ($dt === null) {
            return is_scalar($raw) ? (string) $raw : null;
        }

        return $dt->format($format);
    }

    private function cursorInstant(mixed $raw): ?\DateTimeImmutable
    {
        if ($raw instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($raw)->setTimezone(new \DateTimeZone('UTC'));
        }

        $value = is_scalar($raw) ? trim((string) $raw) : '';
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{10,}$/', $value) === 1) {
            return (new \DateTimeImmutable('@' . substr($value, 0, 10)))->setTimezone(new \DateTimeZone('UTC'));
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{page?: array{start: int, param: string, in: string}, cursor?: array{from_response: string, apply_to: string, format: string}}|null
     */
    private function pagination(array $raw): ?array
    {
        if (isset($raw['pagination'])) {
            return $this->paginationBlock($raw['pagination']);
        }

        $legacy = $this->pageBlock($raw['page'] ?? null);
        if ($legacy === null) {
            return null;
        }

        return ['page' => $legacy];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{page?: array{start: int, param: string, in: string}, cursor?: array{from_response: string, apply_to: string, format: string}}|null
     */
    private function storedPagination(array $raw): ?array
    {
        if (is_array($raw['pagination'] ?? null)) {
            return $raw['pagination'];
        }
        if (is_array($raw['page'] ?? null)) {
            return ['page' => $raw['page']];
        }

        return null;
    }

    /**
     * @return array{page?: array{start: int, param: string, in: string}, cursor?: array{from_response: string, apply_to: string, format: string}}|null
     */
    private function paginationBlock(mixed $raw): ?array
    {
        if ($raw === null || $raw === []) {
            return null;
        }
        if (!is_array($raw)) {
            throw new DomainException('pull_spec.pagination must be an object');
        }

        $page = $this->pageBlock($raw['page'] ?? null);
        $cursor = $this->cursorBlock($raw['cursor'] ?? null);
        if ($page !== null && $cursor !== null) {
            throw new DomainException('pull_spec.pagination must be either page or cursor, not both');
        }
        if ($page === null && $cursor === null) {
            return null;
        }

        return $page !== null ? ['page' => $page] : ['cursor' => $cursor];
    }

    /**
     * @return array{start: int, param: string, in: string, step?: int}|null
     */
    private function pageBlock(mixed $raw): ?array
    {
        if ($raw === null || $raw === []) {
            return null;
        }
        if (!is_array($raw)) {
            throw new DomainException('pull_spec.pagination.page must be an object');
        }

        $in = (string) ($raw['in'] ?? 'body');
        if ($in !== 'body' && $in !== 'query') {
            throw new DomainException('pull_spec.pagination.page.in must be body or query');
        }

        $page = [
            'start' => max(0, (int) ($raw['start'] ?? 1)),
            'param' => (string) ($raw['param'] ?? 'page'),
            'in' => $in,
        ];
        if (isset($raw['step']) && (int) $raw['step'] > 1) {
            $page['step'] = (int) $raw['step'];
        }

        return $page;
    }

    /**
     * @return array{from_response: string, apply_to: string, format: string}|null
     */
    private function cursorBlock(mixed $raw): ?array
    {
        if ($raw === null || $raw === []) {
            return null;
        }
        if (!is_array($raw)) {
            throw new DomainException('pull_spec.pagination.cursor must be an object');
        }

        $fromResponse = $this->dottedPath($raw['from_response'] ?? null, 'pagination.cursor.from_response');
        $applyTo = trim((string) ($raw['apply_to'] ?? 'from'));
        if (!in_array($applyTo, ['from', 'to', 'page'], true)) {
            throw new DomainException('pull_spec.pagination.cursor.apply_to must be from, to or page');
        }

        $format = trim((string) ($raw['format'] ?? PullSpec::DEFAULT_DATE_FORMAT));
        if ($format === '') {
            throw new DomainException('pull_spec.pagination.cursor.format must not be empty');
        }

        return [
            'from_response' => $fromResponse,
            'apply_to' => $applyTo,
            'format' => $format,
        ];
    }
}
