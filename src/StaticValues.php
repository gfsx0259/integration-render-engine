<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine;

/**
 * Values for {{static.*}} in an adapter template.
 * Key is the placeholder name (api_token), not the HTTP header name.
 */
final class StaticValues
{
    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    public static function normalizeMap(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (is_string($value) || is_numeric($value)) {
                $out[$key] = (string) $value;
                continue;
            }

            if (is_array($value) && ($value['source'] ?? 'static') === 'static') {
                $out[$key] = (string) ($value['value'] ?? '');
            }
        }

        return $out;
    }
}
