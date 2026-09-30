<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests;

use Enthusiast\IntegrationRenderEngine\Lead;
use Enthusiast\IntegrationRenderEngine\LeadVars;
use PHPUnit\Framework\TestCase;

final class LeadVarsTest extends TestCase
{
    public function testDerivesCountryAndPhoneForms(): void
    {
        $vars = (new LeadVars())->build(
            new Lead('Taro', 'Yamada', 'taro@example.com', '+81 90-1234-5678', '1.2.3.4', 'jp', subs: ['aff_sub4' => 'Platform']),
            'pub-1',
            'https://receiver/v1/callback/pub-1/sig',
        );

        self::assertSame('JP', $vars['country_code']);
        self::assertSame('jp', $vars['country_code_lower']);
        self::assertSame('ja', $vars['language']);
        self::assertSame('ja_JP', $vars['locale']);
        self::assertSame('819012345678', $vars['phone_digits']);
        self::assertSame('+819012345678', $vars['phone_plus']);
        self::assertSame('81', $vars['phone_dial_code']);
        self::assertSame('9012345678', $vars['phone_national']);
        self::assertSame('Taro Yamada', $vars['full_name']);
        self::assertSame('Platform', $vars['aff_sub4']);
        self::assertSame('pub-1', $vars['lead_id']);
        self::assertSame('https://receiver/v1/callback/pub-1/sig', $vars['callback_url']);
    }

    public function testDialCodeComesFromThePhoneNotFromTheLeadCountry(): void
    {
        $vars = (new LeadVars())->build(
            new Lead('Anna', 'Muller', 'anna@example.com', '+44 7911 123456', '1.2.3.4', 'DE'),
            'pub-2',
        );

        self::assertSame('44', $vars['phone_dial_code']);
        self::assertSame('7911123456', $vars['phone_national']);
        self::assertSame('de', $vars['language']);
    }

    public function testNationalFormatUsesTheLeadCountry(): void
    {
        $vars = (new LeadVars())->build(
            new Lead('Anna', 'Muller', 'anna@example.com', '0151 23456789', '1.2.3.4', 'DE'),
            'pub-3',
        );

        self::assertSame('49', $vars['phone_dial_code']);
        self::assertSame('+4915123456789', $vars['phone_plus']);
    }

    public function testUnparsablePhoneKeepsDigits(): void
    {
        $vars = (new LeadVars())->build(new Lead('A', 'B', 'a@b.co', '12', '1.2.3.4', 'ZZ'), 'pub-4');

        self::assertSame('en', $vars['language']);
        self::assertSame('', $vars['phone_dial_code']);
        self::assertSame('12', $vars['phone_national']);
    }
}
