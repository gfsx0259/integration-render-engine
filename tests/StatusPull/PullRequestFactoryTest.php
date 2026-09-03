<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests\StatusPull;

use Enthusiast\IntegrationRenderEngine\StatusPull\PullRequestFactory;
use Enthusiast\IntegrationRenderEngine\StatusPull\PullSpecParser;
use PHPUnit\Framework\TestCase;

final class PullRequestFactoryTest extends TestCase
{
    public function testJoinsHostFromConnectionUrlAndRendersStaticAndPoll(): void
    {
        $parser = new PullSpecParser();
        $spec = $parser->hydrateAndValidate([
            'method' => 'GET',
            'path' => '/api/v3/get-leads?api_token={{static.api_token}}&from={{poll.from}}',
            'items' => 'data',
            'id' => 'link_id',
            'status' => 'status',
            'status_map' => ['approved' => ['status' => 'approved']],
        ]);
        self::assertNotNull($spec);

        $request = (new PullRequestFactory())->build(
            'http://tracking-dummy/api/v3/integration?api_token=ignored',
            $spec,
            ['api_token' => 'secret'],
            ['from' => '2026-09-01', 'to' => '2026-09-03', 'page' => '1'],
        );

        self::assertSame('GET', $request->method);
        self::assertSame(
            'http://tracking-dummy/api/v3/get-leads?api_token=secret&from=2026-09-01',
            $request->url,
        );
        self::assertNull($request->body);
    }

    public function testPostBodyCastsPageToInt(): void
    {
        $parser = new PullSpecParser();
        $spec = $parser->hydrateAndValidate([
            'method' => 'POST',
            'path' => '/api/pull/customers',
            'headers' => ['x-api-key' => '{{static.conversion_key}}'],
            'body' => [
                'from' => '{{poll.from}}',
                'to' => '{{poll.to}}',
                'page' => '{{poll.page}}',
            ],
            'items' => 'data',
            'id' => 'tracking.MPC_1',
            'status' => 'customerData.call_status',
            'status_map' => ['No Interest' => ['status' => 'rejected', 'reason' => 'not_interested']],
            'page' => ['start' => 1, 'param' => 'page', 'in' => 'body'],
        ]);
        self::assertNotNull($spec);

        $request = (new PullRequestFactory())->build(
            'https://crm.example:8443/ignored',
            $spec,
            ['conversion_key' => 'ck'],
            ['from' => '2026-09-01', 'to' => '2026-09-03', 'page' => '2'],
        );

        self::assertSame('POST', $request->method);
        self::assertSame('https://crm.example:8443/api/pull/customers', $request->url);
        self::assertSame(['x-api-key' => 'ck'], $request->headers);
        self::assertSame([
            'from' => '2026-09-01',
            'to' => '2026-09-03',
            'page' => 2,
        ], $request->body);
    }
}
