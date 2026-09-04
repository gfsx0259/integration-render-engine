<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests\StatusPull;

use Enthusiast\IntegrationRenderEngine\StatusPull\PullSpec;
use Enthusiast\IntegrationRenderEngine\StatusPull\PullSpecParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PullSpecParserTest extends TestCase
{
    private PullSpecParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PullSpecParser();
    }

    public function testElnopyFixtureMapsLinkIdAndStatus(): void
    {
        $spec = $this->spec([
            'method' => 'GET',
            'path' => '/api/v3/get-leads?api_token={{static.api_token}}',
            'items' => 'data',
            'id' => 'link_id',
            'status' => 'status',
            'status_map' => [
                'new' => null,
                'approved' => ['status' => 'approved'],
                'not_interested' => ['status' => 'rejected', 'reason' => 'not_interested'],
            ],
        ]);

        $items = $this->parser->parse([
            'success' => true,
            'data' => [
                ['id' => 12, 'link_id' => 'pub-1', 'status' => 'approved'],
                ['id' => 13, 'link_id' => 'pub-2', 'status' => 'new'],
                ['id' => 14, 'link_id' => 'pub-3', 'status' => 'not_interested'],
            ],
        ], $spec);

        self::assertCount(3, $items);
        self::assertSame('pub-1', $items[0]->publicId);
        self::assertSame('approved', $items[0]->status);
        self::assertTrue($items[1]->inProgress);
        self::assertNull($items[1]->status);
        self::assertSame('rejected', $items[2]->status);
        self::assertSame('not_interested', $items[2]->reason);
    }

    public function testTrackboxNestedCustomerData(): void
    {
        $spec = $this->spec([
            'method' => 'POST',
            'path' => '/api/pull/customers',
            'items' => 'data',
            'id' => 'tracking.MPC_1',
            'status' => 'customerData.call_status',
            'updated' => 'customerData.updated',
            'status_map' => [
                'Call Back' => null,
                'No Answer' => null,
                'No Interest' => ['status' => 'rejected', 'reason' => 'not_interested'],
                'Wrong Info' => ['status' => 'rejected', 'reason' => 'invalid_data'],
            ],
            'also_approved_when' => ['path' => 'customerData.depositor', 'eq' => 1],
        ]);

        $items = $this->parser->parse([
            'status' => true,
            'data' => [
                [
                    'customerData' => [
                        'call_status' => 'Call Back',
                        'depositor' => 1,
                        'updated' => '2026-08-25 17:07:35',
                    ],
                    'tracking' => ['MPC_1' => 'public-ftd'],
                ],
                [
                    'customerData' => [
                        'call_status' => 'No Interest',
                        'depositor' => 0,
                        'updated' => '2026-08-27 07:50:29',
                    ],
                    'tracking' => ['MPC_1' => 'public-ni'],
                ],
                [
                    'customerData' => [
                        'call_status' => 'Call Back',
                        'depositor' => 0,
                    ],
                    'tracking' => ['MPC_1' => 'public-cb'],
                ],
                [
                    'customerData' => [
                        'call_status' => 'Mystery',
                        'depositor' => 0,
                    ],
                    'tracking' => ['MPC_1' => 'public-unk'],
                ],
            ],
        ], $spec);

        self::assertCount(4, $items);
        self::assertSame('approved', $items[0]->status);
        self::assertSame('public-ftd', $items[0]->publicId);
        self::assertSame('rejected', $items[1]->status);
        self::assertSame('not_interested', $items[1]->reason);
        self::assertTrue($items[2]->inProgress);
        self::assertTrue($items[3]->unknown);
        self::assertNull($items[3]->status);
        self::assertSame('2026-08-25 17:07:35', $items[0]->updatedAt);
    }

    public function testEmptySpecIsDisabled(): void
    {
        self::assertNull($this->parser->hydrateAndValidate(null));
        self::assertNull($this->parser->hydrateAndValidate([]));
    }

    public function testHydrateUsesStoredShapeWithoutRevalidating(): void
    {
        $stored = $this->spec([
            'method' => 'GET',
            'path' => '/api/v3/get-leads',
            'items' => 'data',
            'id' => 'link_id',
            'status' => 'status',
            'status_map' => ['approved' => ['status' => 'approved']],
        ])->toArray();

        $hydrated = $this->parser->hydrate($stored);

        self::assertSame('GET', $hydrated->method);
        self::assertSame('/api/v3/get-leads', $hydrated->path);
        self::assertSame('link_id', $hydrated->id);
    }

    public function testPaginationCursorIsStoredUnderOneKey(): void
    {
        $spec = $this->spec([
            'method' => 'POST',
            'path' => '/api/pull/customers',
            'items' => 'data',
            'id' => 'tracking.MPC_1',
            'status' => 'customerData.call_status',
            'status_map' => ['No Interest' => ['status' => 'rejected', 'reason' => 'not_interested']],
            'pagination' => [
                'cursor' => [
                    'from_response' => 'nextToken',
                    'apply_to' => 'from',
                    'format' => 'Y-m-d H:i:s',
                ],
            ],
        ]);

        self::assertNull($spec->page());
        self::assertSame('nextToken', $spec->cursor()['from_response'] ?? null);
        self::assertSame('from', $spec->cursor()['apply_to'] ?? null);
        self::assertSame('Y-m-d H:i:s', $spec->pollDateFormat());
        self::assertArrayHasKey('pagination', $spec->toArray());
        self::assertArrayNotHasKey('page', $spec->toArray());

        $hydrated = $this->parser->hydrate($spec->toArray());
        self::assertSame('nextToken', $hydrated->cursor()['from_response'] ?? null);
    }

    public function testLegacyPageKeyBecomesPaginationPage(): void
    {
        $spec = $this->spec([
            'method' => 'POST',
            'path' => '/api/pull/customers',
            'items' => 'data',
            'id' => 'tracking.MPC_1',
            'status' => 'customerData.call_status',
            'status_map' => ['No Interest' => ['status' => 'rejected', 'reason' => 'not_interested']],
            'page' => ['start' => 1, 'param' => 'page', 'in' => 'body'],
        ]);

        self::assertSame(1, $spec->pageStart());
        self::assertSame(['page' => ['start' => 1, 'param' => 'page', 'in' => 'body']], $spec->pagination);
    }

    public function testPageAndCursorTogetherAreRejected(): void
    {
        $this->expectException(\DomainException::class);

        $this->parser->hydrateAndValidate([
            'method' => 'POST',
            'path' => '/api/pull/customers',
            'items' => 'data',
            'id' => 'tracking.MPC_1',
            'status' => 'customerData.call_status',
            'status_map' => ['No Interest' => ['status' => 'rejected', 'reason' => 'not_interested']],
            'pagination' => [
                'page' => ['start' => 1],
                'cursor' => ['from_response' => 'nextToken', 'apply_to' => 'from'],
            ],
        ]);
    }

    public function testFormatCursorAcceptsTrackboxNumericToken(): void
    {
        $spec = $this->spec([
            'method' => 'POST',
            'path' => '/api/pull/customers',
            'items' => 'data',
            'id' => 'tracking.MPC_1',
            'status' => 'customerData.call_status',
            'status_map' => ['No Interest' => ['status' => 'rejected', 'reason' => 'not_interested']],
            'pagination' => [
                'cursor' => [
                    'from_response' => 'nextToken',
                    'apply_to' => 'from',
                    'format' => 'Y-m-d H:i:s',
                ],
            ],
        ]);

        self::assertSame('2026-07-15 13:57:27', $this->parser->formatCursor('17841238478062376', $spec));
        self::assertSame('2026-08-01 12:00:00', $this->parser->formatCursor('2026-08-01 12:00:00', $spec));
        self::assertNull($this->parser->formatCursor('', $spec));
        self::assertSame('17841238478062376', $this->parser->readCursor(['nextToken' => '17841238478062376'], $spec));
    }

    public function testAuthErrorInJsonBodyIsNotAnEmptyList(): void
    {
        $spec = $this->spec([
            'method' => 'POST',
            'path' => '/api/pull/customers',
            'items' => 'data',
            'id' => 'tracking.MPC_1',
            'status' => 'customerData.call_status',
            'status_map' => ['No Interest' => ['status' => 'rejected', 'reason' => 'not_interested']],
        ]);

        $this->expectException(RuntimeException::class);

        $this->parser->parse([
            'code' => 401,
            'message' => 'User and password doesnt match',
        ], $spec);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function spec(array $raw): PullSpec
    {
        $spec = $this->parser->hydrateAndValidate($raw);
        self::assertInstanceOf(PullSpec::class, $spec);

        return $spec;
    }
}
