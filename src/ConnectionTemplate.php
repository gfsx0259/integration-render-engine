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
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $static
     */
    public function render(mixed $template, array $lead, array $static): mixed
    {
        if (is_array($template)) {
            return array_map(function ($value) use ($static, $lead) {
                return $this->render($value, $lead, $static);
            }, $template);
        }

        if (!is_string($template)) {
            return $template;
        }

        $replaced = preg_replace_callback(
            self::PLACEHOLDER,
            function (array $match) use ($lead, $static): string {
                $source = TemplateSource::from($match[1]);
                $bag = $source === TemplateSource::Lead ? $lead : $static;

                return $this->lookup($bag, $match[2]);
            },
            $template,
        );

        return $replaced ?? $template;
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $static
     * @return array<string, string>
     */
    public function renderHeaders(array $template, array $lead, array $static): array
    {
        $rendered = $this->render($template, $lead, $static);
        if (!is_array($rendered)) {
            return [];
        }

        $headers = [];
        foreach ($rendered as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (is_scalar($value) && (string) $value !== '') {
                $headers[$key] = (string) $value;
            }
        }

        return $headers;
    }

    public function dropEmpty(mixed $rendered): mixed
    {
        if (!is_array($rendered)) {
            return $rendered;
        }

        $kept = array_filter(
            array_map(fn (mixed $value): mixed => $this->dropEmpty($value), $rendered),
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
