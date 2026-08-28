<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests;

use Enthusiast\IntegrationRenderEngine\StaticValues;
use PHPUnit\Framework\TestCase;

final class StaticValuesTest extends TestCase
{
    public function testNormalizeMapKeepsStringAndNumericValues(): void
    {
        self::assertSame(
            ['api_token' => 'secret', 'aff_id' => '42'],
            StaticValues::normalizeMap([
                'api_token' => 'secret',
                'aff_id' => 42,
                '' => 'skip',
                1 => 'skip-int-key',
            ]),
        );
    }

    public function testNormalizeMapAcceptsSourceValueShape(): void
    {
        self::assertSame(
            ['api_token' => 'secret'],
            StaticValues::normalizeMap([
                'api_token' => ['source' => 'static', 'value' => 'secret'],
                'ignored' => ['source' => 'lead', 'value' => 'nope'],
            ]),
        );
    }
}
