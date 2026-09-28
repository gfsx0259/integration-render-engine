<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests;

use Enthusiast\IntegrationRenderEngine\ConnectionTemplate;
use Enthusiast\IntegrationRenderEngine\Macros;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MacrosTest extends TestCase
{
    private const array LEAD = [
        'aff_sub3' => '190',
        'aff_sub4' => 'ZvPlatform',
        'aff_sub5' => '',
        'country_code' => 'JP',
        'language' => 'ja',
        'ip' => '1.2.3.4',
        'password' => 'Pass1234',
        'lead_id' => 'pub-1',
    ];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function values(): iterable
    {
        yield 'whole macro' => ['{aff_sub4}', 'ZvPlatform'];
        yield 'country language' => ['{countryLanguage}', 'ja'];
        yield 'country' => ['{country}', 'JP'];
        yield 'password' => ['{password}', 'Pass1234'];
        yield 'hash is the delivery id' => ['{hash}', 'pub-1'];
        yield 'funnel is always empty' => ['{funnelName}', ''];
        yield 'empty funnel drops its separator' => ['{funnelName}_{aff_sub4}', 'ZvPlatform'];
        yield 'empty funnel at the end' => ['{aff_sub4}-{funnelName}', 'ZvPlatform'];
        yield 'both filled keep separator' => ['{aff_sub4}-{aff_sub3}', 'ZvPlatform-190'];
        yield 'empty lead field drops separator' => ['{aff_sub5}-{aff_sub3}', '190'];
        yield 'macro inside text' => ['x{ip}y', 'x1.2.3.4y'];
        yield 'unknown braces stay literal' => ['pass{2Hm3OI}', 'pass{2Hm3OI}'];
        yield 'literal without macros' => ['fixed-value', 'fixed-value'];
        yield 'engine placeholder untouched' => ['{{lead.email}}', '{{lead.email}}'];
    }

    #[DataProvider('values')]
    public function testExpand(string $value, string $expected): void
    {
        self::assertSame($expected, Macros::expand($value, self::LEAD));
    }

    public function testContainsOnlyKnownMacros(): void
    {
        self::assertTrue(Macros::contains('{aff_sub4}'));
        self::assertTrue(Macros::contains('{funnelName} - {aff_sub4}'));
        self::assertFalse(Macros::contains('pass{2Hm3OI}'));
        self::assertFalse(Macros::contains('{{lead.email}}'));
    }

    public function testExpandAllLeavesNonStrings(): void
    {
        self::assertSame(
            ['FUNNEL' => 'ZvPlatform', 'LIMIT' => 5],
            Macros::expandAll(['FUNNEL' => '{aff_sub4}', 'LIMIT' => 5], self::LEAD),
        );
    }

    public function testDependsOnLeadThroughStaticMacro(): void
    {
        $engine = new ConnectionTemplate();
        $static = ['API_TOKEN' => 'secret', 'SOURCE' => '{aff_sub4}', 'LINK' => '{{lead.lead_id}}'];

        self::assertSame(['SOURCE', 'LINK'], $engine->leadDependentKeys($static));
        self::assertTrue($engine->dependsOnLead('{{static.SOURCE}}', $static));
        self::assertTrue($engine->dependsOnLead('{{lead.email}}', $static));
        self::assertFalse($engine->dependsOnLead('Bearer {{static.API_TOKEN}}', $static));
    }

    public function testDropEmptyKeepsListsAndNestedObjects(): void
    {
        $engine = new ConnectionTemplate();

        self::assertSame(
            ['a' => 'x', 'leads' => [['name' => 'n']], 'flag' => false, 'zero' => '0'],
            $engine->dropEmpty(['a' => 'x', 'b' => '', 'leads' => [['name' => 'n', 'mail' => '']], 'flag' => false, 'zero' => '0']),
        );
    }

    public function testRenderHeadersSkipsEmptyValues(): void
    {
        $engine = new ConnectionTemplate();

        self::assertSame(
            ['Authorization' => 'k'],
            $engine->renderHeaders(['Authorization' => '{{static.KEY}}', 'X-Sub' => '{{static.SUB}}'], [], ['KEY' => 'k', 'SUB' => '']),
        );
    }
}
