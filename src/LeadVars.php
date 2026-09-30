<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

final readonly class LeadVars
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

    private PhoneNumberUtil $phones;

    public function __construct()
    {
        $this->phones = PhoneNumberUtil::getInstance();
    }

    /**
     * @return array<string, string>
     */
    public function build(Lead $lead, string $leadId, string $callbackUrl = ''): array
    {
        $country = strtoupper($lead->countryCode);
        $language = self::LANGUAGE_BY_COUNTRY[$country] ?? self::DEFAULT_LANGUAGE;
        [$dialCode, $national] = $this->splitPhone($lead->phone, $country);
        $digits = $dialCode . $national;

        return [
            ...$lead->subs,
            'first_name' => $lead->firstName,
            'last_name' => $lead->lastName,
            'full_name' => trim($lead->firstName . ' ' . $lead->lastName),
            'email' => $lead->email,
            'phone' => $lead->phone,
            'phone_digits' => $digits,
            'phone_plus' => $digits === '' ? '' : '+' . $digits,
            'phone_dial_code' => $dialCode,
            'phone_national' => $national,
            'ip' => $lead->ip,
            'password' => $lead->password,
            'offer_id' => $lead->offerId,
            'country_code' => $country,
            'country_code_lower' => strtolower($country),
            'language' => $language,
            'language_upper' => strtoupper($language),
            'locale' => $language . '_' . $country,
            'lead_id' => $leadId,
            'callback_url' => $callbackUrl,
            'sent_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{string, string}
     */
    private function splitPhone(string $phone, string $country): array
    {
        try {
            $number = $this->phones->parse($phone, $country);
        } catch (NumberParseException) {
            return ['', preg_replace('/\D+/', '', $phone) ?? ''];
        }

        return [(string) $number->getCountryCode(), $this->phones->getNationalSignificantNumber($number)];
    }
}
