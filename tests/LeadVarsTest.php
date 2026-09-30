<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests;

use Enthusiast\IntegrationRenderEngine\LeadVars;
use PHPUnit\Framework\TestCase;

final class LeadVarsTest extends TestCase
{
    public function testDerivesCountryAndPhoneForms(): void
    {
        $vars = LeadVars::build([
            'first_name' => 'Taro',
            'last_name' => 'Yamada',
            'phone' => '+81 90-1234-5678',
            'country_code' => 'jp',
        ], ['lead_id' => 'pub-1']);

        self::assertSame('JP', $vars['country_code']);
        self::assertSame('jp', $vars['country_code_lower']);
        self::assertSame('ja', $vars['language']);
        self::assertSame('ja_JP', $vars['locale']);
        self::assertSame('819012345678', $vars['phone_digits']);
        self::assertSame('+819012345678', $vars['phone_plus']);
        self::assertSame('81', $vars['phone_dial_code']);
        self::assertSame('9012345678', $vars['phone_national']);
        self::assertSame('Taro Yamada', $vars['full_name']);
        self::assertSame('pub-1', $vars['lead_id']);
    }

    public function testUnknownCountryFallsBackToEnglishAndKeepsDigits(): void
    {
        $vars = LeadVars::build(['phone' => '12345', 'country_code' => 'ZZ']);

        self::assertSame('en', $vars['language']);
        self::assertSame('', $vars['phone_dial_code']);
        self::assertSame('12345', $vars['phone_national']);
    }
}
