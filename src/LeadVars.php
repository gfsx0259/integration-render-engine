<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine;

final class LeadVars
{
    private const array LANGUAGE_BY_COUNTRY = [
        'AE' => 'ar', 'AR' => 'es', 'AT' => 'de', 'AU' => 'en', 'BE' => 'nl', 'BG' => 'bg', 'BR' => 'pt', 'CA' => 'en',
        'CH' => 'de', 'CL' => 'es', 'CO' => 'es', 'CZ' => 'cs', 'DE' => 'de', 'DK' => 'da', 'EE' => 'et', 'ES' => 'es',
        'FI' => 'fi', 'FR' => 'fr', 'GB' => 'en', 'GR' => 'el', 'HR' => 'hr', 'HU' => 'hu', 'IE' => 'en', 'IL' => 'he',
        'IN' => 'en', 'IT' => 'it', 'JP' => 'ja', 'KR' => 'ko', 'LT' => 'lt', 'LV' => 'lv', 'MX' => 'es', 'MY' => 'ms',
        'NL' => 'nl', 'NO' => 'no', 'NZ' => 'en', 'PE' => 'es', 'PH' => 'en', 'PL' => 'pl', 'PT' => 'pt', 'RO' => 'ro',
        'RS' => 'sr', 'RU' => 'ru', 'SA' => 'ar', 'SE' => 'sv', 'SG' => 'en', 'SI' => 'sl', 'SK' => 'sk', 'TH' => 'th',
        'TR' => 'tr', 'UA' => 'uk', 'US' => 'en', 'VN' => 'vi', 'ZA' => 'en',
    ];

    private const string DEFAULT_LANGUAGE = 'en';

    private const array DIAL_CODE_BY_COUNTRY = [
        'AE' => '971', 'AR' => '54', 'AT' => '43', 'AU' => '61', 'BE' => '32', 'BG' => '359', 'BR' => '55', 'CA' => '1',
        'CH' => '41', 'CL' => '56', 'CO' => '57', 'CZ' => '420', 'DE' => '49', 'DK' => '45', 'EE' => '372', 'ES' => '34',
        'FI' => '358', 'FR' => '33', 'GB' => '44', 'GR' => '30', 'HR' => '385', 'HU' => '36', 'IE' => '353', 'IL' => '972',
        'IN' => '91', 'IT' => '39', 'JP' => '81', 'KR' => '82', 'LT' => '370', 'LV' => '371', 'MX' => '52', 'MY' => '60',
        'NL' => '31', 'NO' => '47', 'NZ' => '64', 'PE' => '51', 'PH' => '63', 'PL' => '48', 'PT' => '351', 'RO' => '40',
        'RS' => '381', 'RU' => '7', 'SA' => '966', 'SE' => '46', 'SG' => '65', 'SI' => '386', 'SK' => '421', 'TH' => '66',
        'TR' => '90', 'UA' => '380', 'US' => '1', 'VN' => '84', 'ZA' => '27',
    ];

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function build(array $lead, array $extra = []): array
    {
        $country = strtoupper((string) ($lead['country_code'] ?? ''));
        $language = self::LANGUAGE_BY_COUNTRY[$country] ?? self::DEFAULT_LANGUAGE;
        $digits = preg_replace('/\D+/', '', (string) ($lead['phone'] ?? '')) ?? '';
        $dialCode = self::DIAL_CODE_BY_COUNTRY[$country] ?? '';
        $national = $dialCode !== '' && str_starts_with($digits, $dialCode) ? substr($digits, strlen($dialCode)) : $digits;

        return array_merge($lead, [
            'country_code' => $country,
            'country_code_lower' => strtolower($country),
            'language' => $language,
            'language_upper' => strtoupper($language),
            'locale' => $language . '_' . $country,
            'phone_digits' => $digits,
            'phone_plus' => $digits === '' ? '' : '+' . $digits,
            'phone_dial_code' => $dialCode,
            'phone_national' => $national,
            'full_name' => trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')),
            'sent_at' => gmdate('Y-m-d H:i:s'),
        ], $extra);
    }
}
