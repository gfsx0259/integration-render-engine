<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Push;

use InvalidArgumentException;

final class ResponseSpecParser
{
    private const int ERROR_MAX_LENGTH = 500;

    /**
     * @param array<string, mixed>|null $raw
     */
    public function hydrateAndValidate(?array $raw): ?ResponseSpec
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        $ok = $raw['ok'] ?? null;
        if ($ok !== null) {
            if (!is_array($ok) || !is_string($ok['path'] ?? null) || $ok['path'] === '' || !array_key_exists('eq', $ok)) {
                throw new InvalidArgumentException('response_spec.ok must be {path, eq}');
            }
            if (!is_scalar($ok['eq']) && $ok['eq'] !== null) {
                throw new InvalidArgumentException('response_spec.ok.eq must be a scalar');
            }
        }

        foreach (['external_id', 'autologin', 'error'] as $field) {
            $value = $raw[$field] ?? null;
            if ($value !== null && (!is_string($value) || $value === '')) {
                throw new InvalidArgumentException(sprintf('response_spec.%s must be a dotted path', $field));
            }
        }

        return new ResponseSpec(
            ok: $ok === null ? null : ['path' => $ok['path'], 'eq' => $ok['eq']],
            externalId: $raw['external_id'] ?? null,
            autologin: $raw['autologin'] ?? null,
            error: $raw['error'] ?? null,
        );
    }

    public function verdict(?ResponseSpec $spec, int $code, string $body): PushVerdict
    {
        $decoded = json_decode($body, true);
        $data = is_array($decoded) ? $decoded : [];

        $externalId = $spec?->externalId === null ? null : $this->scalar($this->lookup($data, $spec->externalId));
        $autologin = $spec?->autologin === null ? null : $this->scalar($this->lookup($data, $spec->autologin));
        $error = $spec?->error === null ? null : $this->scalar($this->lookup($data, $spec->error));

        if ($code < 200 || $code >= 300) {
            return new PushVerdict(
                PushVerdict::ERROR,
                $code,
                $externalId,
                $autologin,
                $this->errorText($error, $body),
            );
        }

        if ($spec?->ok !== null && !$this->equals($this->lookup($data, $spec->ok['path']), $spec->ok['eq'])) {
            return new PushVerdict(
                PushVerdict::REJECTED,
                $code,
                $externalId,
                $autologin,
                $this->errorText($error, $body),
            );
        }

        return new PushVerdict(PushVerdict::ACCEPTED, $code, $externalId, $autologin, $error);
    }

    private function errorText(?string $error, string $body): ?string
    {
        $text = $error ?? trim($body);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, self::ERROR_MAX_LENGTH);
    }

    private function equals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($expected)) {
            if (is_string($actual)) {
                return in_array(strtolower($actual), $expected ? ['1', 'true', 'ok', 'success'] : ['0', 'false', 'fail', 'error'], true);
            }

            return (bool) $actual === $expected;
        }

        if (is_int($expected) || is_float($expected)) {
            return is_numeric($actual) && (float) $actual == (float) $expected;
        }

        if ($expected === null) {
            return $actual === null;
        }

        return strcasecmp((string) $this->scalar($actual), (string) $expected) === 0;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function lookup(array $node, string $path): mixed
    {
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
        if ($value === null || is_array($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
