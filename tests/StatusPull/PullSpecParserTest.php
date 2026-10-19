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

    public function testDepositListMarksEveryListedLeadAsDeposit(): void
    {
        $spec = $this->spec([
            'method' => 'GET',
            'path' => '{{static.API_URL}}/affiliate_deposits?token={{static.API_TOKEN}}&from={{poll.from}}&to={{poll.to}}&limit=3000',
            'items' => '',
            'id' => 'id',
            'status' => 'sale_status',
            'date_format' => 'Y-m-d\TH:i:s.v\Z',
            'deposits' => [
                'method' => 'GET',
                'path' => '{{static.API_URL}}/affiliate_deposits?token={{static.API_TOKEN}}&from={{poll.from}}&to={{poll.to}}&filter=ftd&limit=3000',
                'items' => '',
                'id' => 'id',
                'date_format' => 'Y-m-d\TH:i:s.v\Z',
            ],
        ]);

        self::assertNotNull($spec->deposits);
        self::assertTrue($spec->deposits->depositList);
        self::assertSame(PullSpec::DEFAULT_DEPOSIT_LOOKBACK_DAYS, $spec->deposits->lookbackDays);

        $deposits = $this->parser->parse([
            ['id' => 3652603, 'first_time_deposit' => '2026-10-08 08:09:02', 'sale_status' => 'FTD'],
            ['id' => 3651403, 'first_time_deposit' => '2026-10-08 08:08:18', 'sale_status' => 'No Answer'],
        ], $spec->deposits);

        self::assertCount(2, $deposits);
        self::assertSame('3652603', $deposits[0]->publicId);
        self::assertSame('approved', $deposits[0]->status);
        self::assertSame('approved', $deposits[1]->status);
    }

    public function testDepositSpecSurvivesStorageRoundTrip(): void
    {
        $spec = $this->spec([
            'method' => 'POST',
            'path' => '{{static.API_URL}}?act=get_leads_status',
            'body_encoding' => 'form',
            'body' => ['ApiKey' => '{{static.API_KEY}}', 'DateFrom' => '{{poll.from}}', 'DateTo' => '{{poll.to}}'],
            'items' => 'ret_message',
            'id' => 'leadid',
            'status' => 'status',
            'date_format' => 'Y-m-d H:i:s',
            'pagination' => ['page' => ['start' => 0, 'param' => 'skip', 'in' => 'query', 'step' => 1000]],
            'deposits' => [
                'method' => 'POST',
                'path' => '{{static.API_URL}}?act=get_depositors',
                'body_encoding' => 'form',
                'body' => ['ApiKey' => '{{static.API_KEY}}', 'DateFrom' => '{{poll.from}}', 'DateTo' => '{{poll.to}}'],
                'items' => 'ret_message.deposits',
                'id' => 'leadid',
                'lookback_days' => 45,
            ],
        ]);

        $stored = $spec->toArray();
        self::assertSame('form', $stored['body_encoding']);
        self::assertSame('Y-m-d H:i:s', $stored['date_format']);
        self::assertSame(1000, $stored['pagination']['page']['step']);
        self::assertArrayNotHasKey('status_map', $stored['deposits']);
        self::assertArrayNotHasKey('status', $stored['deposits']);

        $restored = $this->parser->hydrate($stored);
        self::assertSame(PullSpec::ENCODING_FORM, $restored->bodyEncoding);
        self::assertSame(1000, $restored->pageStep());
        self::assertNotNull($restored->deposits);
        self::assertTrue($restored->deposits->depositList);
        self::assertSame(45, $restored->deposits->lookbackDays);
        self::assertSame('ret_message.deposits', $restored->deposits->items);
        self::assertSame($stored, $restored->toArray());
    }

    public function testNotEmptyDepositFieldApprovesOnlyFilledRows(): void
    {
        $spec = $this->spec([
            'method' => 'POST',
            'path' => '{{static.API_URL}}/api/pull-leads',
            'items' => 'data',
            'id' => 'leadId',
            'status' => 'status',
            'also_approved_when' => ['path' => 'ftd', 'not_empty' => true],
        ]);

        $items = $this->parser->parse(['data' => [
            ['leadId' => 249807, 'ftd' => null, 'status' => 'reshuffled'],
            ['leadId' => 249808, 'ftd' => '0000-00-00 00:00:00', 'status' => 'call back'],
            ['leadId' => 249809, 'ftd' => '2026-10-07 12:39:41', 'status' => 'not interested'],
        ]], $spec);

        self::assertNull($items[0]->status);
        self::assertNull($items[1]->status);
        self::assertSame('approved', $items[2]->status);
        self::assertSame('not interested', $items[2]->rawStatus);
    }

    public function testStatusTextDoesNotApproveWhenTheAdapterHasADepositSignal(): void
    {
        $spec = $this->spec([
            'method' => 'GET',
            'path' => '{{static.API_URL}}/api/v3/get-leads?api_token={{static.API_TOKEN}}',
            'items' => 'data',
            'id' => 'id',
            'status' => 'status',
            'also_approved_when' => ['path' => 'acq', 'eq' => 1],
            'status_map' => ['Sent' => ['status' => 'approved']],
        ]);

        $items = $this->parser->parse(['data' => [
            ['id' => 1, 'acq' => 0, 'status' => 'Sent'],
            ['id' => 2, 'acq' => 0, 'status' => 'Deposited'],
            ['id' => 3, 'acq' => 1, 'status' => 'Call back'],
        ]], $spec);

        self::assertTrue($items[0]->inProgress);
        self::assertTrue($items[1]->inProgress);
        self::assertSame('approved', $items[2]->status);
    }

    public function testStatusTextStillApprovesWithoutADepositSignal(): void
    {
        $spec = $this->spec([
            'method' => 'GET',
            'path' => '/api/leads',
            'items' => 'data',
            'id' => 'id',
            'status' => 'status',
        ]);

        $items = $this->parser->parse(['data' => [['id' => 1, 'status' => 'FTD']]], $spec);

        self::assertSame('approved', $items[0]->status);
    }

    public function testPollDatesFormatAsUnixOrPattern(): void
    {
        $at = new \DateTimeImmutable('2026-10-19 05:10:49', new \DateTimeZone('UTC'));
        $base = ['method' => 'GET', 'path' => '/leads', 'items' => 'data', 'id' => 'id', 'status' => 'status'];

        self::assertSame('1792386649', $this->spec($base + ['date_format' => 'unix'])->formatPollDate($at));
        self::assertSame('1792386649000', $this->spec($base + ['date_format' => 'unix_ms'])->formatPollDate($at));
        self::assertSame('2026-10-19T05:10:49.000Z', $this->spec($base + ['date_format' => 'Y-m-d\TH:i:s.v\Z'])->formatPollDate($at));
        self::assertSame('2026-10-19', $this->spec($base)->formatPollDate($at));
    }

    public function testDepositListNeedsAnId(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('pull_spec.deposits.id');

        $this->spec([
            'method' => 'GET',
            'path' => '/leads',
            'items' => 'data',
            'id' => 'id',
            'status' => 'status',
            'deposits' => ['method' => 'GET', 'path' => '/ftds', 'items' => 'data'],
        ]);
    }

    public function testUnknownBodyEncodingIsRejected(): void
    {
        $this->expectException(\DomainException::class);

        $this->spec([
            'method' => 'POST',
            'path' => '/leads',
            'items' => 'data',
            'id' => 'id',
            'status' => 'status',
            'body_encoding' => 'xml',
        ]);
    }

    public function testDepositListKeepsOnlyRowsWithTheDepositSignal(): void
    {
        $spec = $this->spec([
            'method' => 'GET',
            'path' => '{{static.API_URL}}/api/external/integration/lead',
            'items' => 'rows',
            'id' => 'id',
            'status' => 'registration.rawStatus',
            'also_approved_when' => ['path' => 'isDeposited', 'eq' => true],
            'deposits' => [
                'method' => 'GET',
                'path' => '{{static.API_URL}}/api/external/integration/lead',
                'items' => 'rows',
                'id' => 'id',
                'lookback_days' => 30,
                'also_approved_when' => ['path' => 'isDeposited', 'eq' => true],
            ],
        ]);
        self::assertNotNull($spec->deposits);

        $deposits = $this->parser->parse(['rows' => [
            ['id' => 'a1', 'isDeposited' => false, 'registration' => ['rawStatus' => 'Call again']],
            ['id' => 'a2', 'isDeposited' => true, 'registration' => ['rawStatus' => 'No answer']],
        ]], $spec->deposits);

        self::assertCount(1, $deposits);
        self::assertSame('a2', $deposits[0]->publicId);
        self::assertSame('approved', $deposits[0]->status);
        self::assertSame(['path' => 'isDeposited', 'eq' => true], $spec->toArray()['deposits']['also_approved_when']);
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
