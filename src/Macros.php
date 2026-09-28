<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine;

final class Macros
{
    private const string PATTERN = '/\{([A-Za-z][A-Za-z0-9_]*)\}/';
    private const string SEPARATORS = '-_|./ ';

    private const array LEAD_FIELDS = [
        'aff_sub' => 'aff_sub',
        'aff_sub2' => 'aff_sub2',
        'aff_sub3' => 'aff_sub3',
        'aff_sub4' => 'aff_sub4',
        'aff_sub5' => 'aff_sub5',
        'aff_sub6' => 'aff_sub6',
        'aff_sub7' => 'aff_sub7',
        'aff_sub8' => 'aff_sub8',
        'aff_sub9' => 'aff_sub9',
        'aff_sub10' => 'aff_sub10',
        'aff_sub11' => 'aff_sub11',
        'aff_sub12' => 'aff_sub12',
        'aff_sub13' => 'aff_sub13',
        'aff_sub14' => 'aff_sub14',
        'aff_sub15' => 'aff_sub15',
        'aff_sub16' => 'aff_sub16',
        'aff_sub17' => 'aff_sub17',
        'aff_sub18' => 'aff_sub18',
        'aff_sub19' => 'aff_sub19',
        'aff_sub20' => 'aff_sub20',
        'country' => 'country_code',
        'countryLanguage' => 'language',
        'leadLanguage' => 'language',
        'affiliate_id' => 'affiliate_id',
        'offer_id' => 'offer_id',
        'ip' => 'ip',
        'email' => 'email',
        'password' => 'password',
        'hash' => 'lead_id',
        'funnelName' => null,
        'funnelUrl' => null,
        'tpUuid' => null,
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::LEAD_FIELDS);
    }

    public static function contains(string $value): bool
    {
        if (preg_match_all(self::PATTERN, $value, $matches) === false) {
            return false;
        }

        return array_intersect($matches[1], self::names()) !== [];
    }

    /**
     * @param array<string, mixed> $lead
     */
    public static function expand(string $value, array $lead): string
    {
        $emptied = false;

        $expanded = preg_replace_callback(
            self::PATTERN,
            static function (array $match) use ($lead, &$emptied): string {
                if (!array_key_exists($match[1], self::LEAD_FIELDS)) {
                    return $match[0];
                }

                $field = self::LEAD_FIELDS[$match[1]];
                $resolved = $field === null || !is_scalar($lead[$field] ?? null) ? '' : trim((string) $lead[$field]);

                if ($resolved === '') {
                    $emptied = true;
                }

                return $resolved;
            },
            $value,
        ) ?? $value;

        if (!$emptied) {
            return $expanded;
        }

        return trim((string) preg_replace('/([-_|.\/ ])\1+/', '$1', $expanded), self::SEPARATORS);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $lead
     * @return array<string, mixed>
     */
    public static function expandAll(array $values, array $lead): array
    {
        return array_map(
            static fn (mixed $value): mixed => is_string($value) ? self::expand($value, $lead) : $value,
            $values,
        );
    }
}
