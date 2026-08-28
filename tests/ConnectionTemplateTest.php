<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests;

use Enthusiast\IntegrationRenderEngine\ConnectionTemplate;
use Enthusiast\IntegrationRenderEngine\TemplateSource;
use PHPUnit\Framework\TestCase;

final class ConnectionTemplateTest extends TestCase
{
    private ConnectionTemplate $engine;

    protected function setUp(): void
    {
        $this->engine = new ConnectionTemplate();
    }

    public function testRenderReplacesLeadAndStaticPlaceholders(): void
    {
        $rendered = $this->engine->render(
            [
                'email' => '{{lead.email}}',
                'aff_id' => '{{static.affiliate_id}}',
                'event' => 'lead',
            ],
            ['email' => 'a@b.c'],
            ['affiliate_id' => '42'],
        );

        self::assertSame(
            [
                'email' => 'a@b.c',
                'aff_id' => '42',
                'event' => 'lead',
            ],
            $rendered,
        );
    }

    public function testRenderWalksNestedArraysAndDottedPaths(): void
    {
        $rendered = $this->engine->render(
            ['user' => ['email' => '{{lead.contact.email}}']],
            ['contact' => ['email' => 'nested@x.y']],
            [],
        );

        self::assertSame(['user' => ['email' => 'nested@x.y']], $rendered);
    }

    public function testMissingPlaceholderBecomesEmptyString(): void
    {
        self::assertSame('', $this->engine->render('{{lead.missing}}', [], []));
        self::assertSame('Bearer ', $this->engine->render('Bearer {{static.api_token}}', [], []));
    }

    public function testRenderHeadersFlattensToStringMap(): void
    {
        $headers = $this->engine->renderHeaders(
            [
                'Authorization' => 'Bearer {{static.api_token}}',
                'X-Click' => '{{lead.click_id}}',
                'nested' => ['ignored' => '1'],
                0 => 'skip-numeric-key',
            ],
            ['click_id' => 'c1'],
            ['api_token' => 'secret'],
        );

        self::assertSame(
            [
                'Authorization' => 'Bearer secret',
                'X-Click' => 'c1',
            ],
            $headers,
        );
    }

    public function testKeysCollectsUniquePlaceholderNames(): void
    {
        $template = [
            'Authorization' => 'Bearer {{static.api_token}}',
            'body' => '{{lead.email}} {{static.api_token}}',
        ];

        self::assertSame(['api_token'], $this->engine->keys($template, TemplateSource::Static));
        self::assertSame(['email'], $this->engine->keys($template, TemplateSource::Lead));
    }

    public function testAllowsWhitespaceInsidePlaceholders(): void
    {
        self::assertSame('ok', $this->engine->render('{{ lead.email }}', ['email' => 'ok'], []));
    }
}
