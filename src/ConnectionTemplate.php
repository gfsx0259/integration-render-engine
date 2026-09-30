<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine;

/**
 * Adapter template: headers/body structure with placeholders.
 *
 * {{lead.email}}     — from lead data
 * {{static.api_token}} — from user_connections.header_values / field_values
 *
 * Literals without placeholders stay as-is:
 * "Content-Type": "application/json"
 */
final readonly class ConnectionTemplate
{
    private const string PLACEHOLDER = '/\{\{\s*(lead|static)\.([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}/';

    /**
     * @param array<array-key, mixed> $template
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $static
     * @return array<array-key, mixed>
     */
    public function render(array $template, array $lead, array $static): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            is_array($value) => $this->render($value, $lead, $static),
            is_string($value) => $this->substitute($value, $lead, $static),
            default => $value,
        }, $template);
    }

    /**
     * @param array<string, string> $static
     */
    public function renderUrl(string $template, array $static): string
    {
        [$path, $query] = array_pad(explode('?', $template, 2), 2, null);
        $url = $this->substitute($path, [], array_map(static fn (string $value): string => rtrim($value, '/'), $static));

        return $query === null ? $url : $url . '?' . $this->substitute($query, [], array_map('rawurlencode', $static));
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $static
     * @return array<string, string>
     */
    public function renderHeaders(array $template, array $lead, array $static): array
    {
        $headers = [];
        foreach ($this->render($template, $lead, $static) as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (is_scalar($value) && (string) $value !== '') {
                $headers[$key] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<array-key, mixed> $rendered
     * @return array<array-key, mixed>
     */
    public function dropEmpty(array $rendered): array
    {
        $kept = array_filter(
            array_map(fn (mixed $value): mixed => is_array($value) ? $this->dropEmpty($value) : $value, $rendered),
            static fn (mixed $value): bool => $value !== '',
        );

        return array_is_list($rendered) ? array_values($kept) : $kept;
    }

    /**
     * @param array<string, mixed> $static
     */
    public function dependsOnLead(mixed $template, array $static): bool
    {
        return $this->keys($template, TemplateSource::Lead) !== []
            || array_intersect($this->keys($template, TemplateSource::Static), $this->leadDependentKeys($static)) !== [];
    }

    /**
     * @param array<string, mixed> $static
     * @return list<string>
     */
    public function leadDependentKeys(array $static): array
    {
        $keys = [];

        foreach ($static as $key => $value) {
            if (is_string($value) && (Macros::contains($value) || $this->keys($value, TemplateSource::Lead) !== [])) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    /**
     * Placeholder names {{lead.*}} / {{static.*}} from a template.
     *
     * @return list<string>
     */
    public function keys(mixed $template, TemplateSource $source): array
    {
        $keys = [];
        $this->collectKeys($template, $source, $keys);

        return array_keys($keys);
    }

    /**
     * @param array<string, true> $keys
     */
    private function collectKeys(mixed $template, TemplateSource $source, array &$keys): void
    {
        if (is_array($template)) {
            foreach ($template as $value) {
                $this->collectKeys($value, $source, $keys);
            }

            return;
        }

        if (!is_string($template)) {
            return;
        }

        if (preg_match_all(self::PLACEHOLDER, $template, $matches, PREG_SET_ORDER) === false) {
            return;
        }

        foreach ($matches as $match) {
            if ($match[1] === $source->value) {
                $keys[$match[2]] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $static
     */
    private function substitute(string $template, array $lead, array $static): string
    {
        return preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $match): string => $this->lookup(
                TemplateSource::from($match[1]) === TemplateSource::Lead ? $lead : $static,
                $match[2],
            ),
            $template,
        ) ?? $template;
    }

    /**
     * @param array<string, mixed> $bag
     */
    private function lookup(array $bag, string $path): string
    {
        $current = $bag;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return '';
            }
            $current = $current[$segment];
        }

        if ($current === null) {
            return '';
        }

        return is_scalar($current) ? (string) $current : '';
    }
}
