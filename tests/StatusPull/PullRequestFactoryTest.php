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

    public function testAbsolutePathFromSettingsWinsOverConnectionHost(): void
    {
        $spec = (new PullSpecParser())->hydrateAndValidate([
            'method' => 'GET',
            'path' => '{{static.API_URL}}/api/v3/get-leads?api_token={{static.API_TOKEN}}',
            'items' => 'data',
            'id' => 'id',
            'status' => 'status',
            'pagination' => ['page' => ['start' => 1, 'param' => 'page', 'in' => 'query']],
        ]);
        self::assertNotNull($spec);

        $request = (new PullRequestFactory())->build(
            'https://push.example.com/api/v3/integration?api_token=ignored',
            $spec,
            ['API_URL' => 'https://pull.example.com/', 'API_TOKEN' => 'secret'],
            ['from' => '2026-09-01', 'to' => '2026-09-03', 'page' => '2'],
        );

        self::assertSame('https://pull.example.com/api/v3/get-leads?api_token=secret&page=2', $request->url);
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
        self::assertNotNull($spec->page());

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

    public function testPaginationPageBlockCastsPageToInt(): void
    {
        $parser = new PullSpecParser();
        $spec = $parser->hydrateAndValidate([
            'method' => 'POST',
            'path' => '/api/pull/customers',
            'body' => [
                'from' => '{{poll.from}}',
                'to' => '{{poll.to}}',
                'page' => '{{poll.page}}',
            ],
            'items' => 'data',
            'id' => 'tracking.MPC_1',
            'status' => 'customerData.call_status',
            'status_map' => ['No Interest' => ['status' => 'rejected', 'reason' => 'not_interested']],
            'pagination' => [
                'page' => ['start' => 0, 'param' => 'page', 'in' => 'body'],
            ],
        ]);
        self::assertNotNull($spec);

        $request = (new PullRequestFactory())->build(
            'https://crm.example',
            $spec,
            [],
            ['from' => '2026-06-25 14:57:55', 'to' => '2026-09-03 23:59:59', 'page' => '0'],
        );

        self::assertSame(0, $request->body['page'] ?? null);
        self::assertSame('2026-06-25 14:57:55', $request->body['from'] ?? null);
    }

    public function testFormBodyIsSentAsFormFields(): void
    {
        $spec = (new PullSpecParser())->hydrateAndValidate([
            'method' => 'POST',
            'path' => '{{static.API_URL}}?act=get_depositors',
            'body_encoding' => 'form',
            'body' => ['ApiKey' => '{{static.API_KEY}}', 'DateFrom' => '{{poll.from}}'],
            'items' => 'ret_message.deposits',
            'id' => 'leadid',
            'status' => 'status',
        ]);
        self::assertNotNull($spec);

        $request = (new PullRequestFactory())->build(
            'https://push.example.com/repost.php',
            $spec,
            ['API_URL' => 'https://tracker.example.com/repost.php', 'API_KEY' => 'k1'],
            ['from' => '2026-10-05 05:10:49', 'to' => '2026-10-19 05:10:49', 'page' => '1'],
        );

        self::assertSame('https://tracker.example.com/repost.php?act=get_depositors', $request->url);
        self::assertSame(
            ['form_params' => ['ApiKey' => 'k1', 'DateFrom' => '2026-10-05 05:10:49']],
            $request->bodyOptions(),
        );
    }

    public function testGetMayCarryAJsonBody(): void
    {
        $spec = (new PullSpecParser())->hydrateAndValidate([
            'method' => 'GET',
            'path' => '{{static.API_URL}}/api/web-master/leads',
            'body' => ['date_start' => '{{poll.from}}', 'is_action' => '1'],
            'items' => 'data.data',
            'id' => 'id',
            'status' => 'status',
        ]);
        self::assertNotNull($spec);

        $request = (new PullRequestFactory())->build(
            'https://crm.example',
            $spec,
            ['API_URL' => 'https://crm.example'],
            ['from' => '2026-10-05', 'to' => '2026-10-20', 'page' => '1'],
        );

        self::assertSame('GET', $request->method);
        self::assertSame(['json' => ['date_start' => '2026-10-05', 'is_action' => '1']], $request->bodyOptions());
    }

    public function testQueryEncodingMovesBodyIntoTheUrlAndStepsTheOffset(): void
    {
        $spec = (new PullSpecParser())->hydrateAndValidate([
            'method' => 'GET',
            'path' => '{{static.API_URL}}/api/system/v1/leads/search',
            'body_encoding' => 'query',
            'body' => ['count' => '1000', 'ftdDateFrom' => '{{poll.from}}'],
            'items' => 'items',
            'id' => 'leadId',
            'status' => 'status',
            'pagination' => ['page' => ['start' => 0, 'param' => 'skip', 'in' => 'query', 'step' => 1000]],
        ]);
        self::assertNotNull($spec);

        $request = (new PullRequestFactory())->build(
            'https://crm.example',
            $spec,
            ['API_URL' => 'https://crm.example'],
            ['from' => '2026-10-05T05:10:49', 'to' => '2026-10-19T05:10:49', 'page' => '1000'],
        );

        self::assertSame(
            'https://crm.example/api/system/v1/leads/search?skip=1000&count=1000&ftdDateFrom=2026-10-05T05%3A10%3A49',
            $request->url,
        );
        self::assertNull($request->body);
        self::assertSame([], $request->bodyOptions());
        self::assertSame(1000, $spec->pageStep());
    }
}
