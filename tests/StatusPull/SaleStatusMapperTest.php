<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests\StatusPull;

use Enthusiast\IntegrationRenderEngine\StatusPull\PullSpecParser;
use Enthusiast\IntegrationRenderEngine\StatusPull\SaleStatusMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SaleStatusMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function statuses(): iterable
    {
        foreach ([
            'depositor', 'approved', 'deposit', 'deposited', 'Deposit', 'ftd', 'ftd - no answer', 'ftd external',
            'successful ftd', 'has deposited', 'first deposit', 'deposited with me', 'self depositor',
        ] as $status) {
            yield $status => [$status, 'approved'];
        }

        foreach ([
            null, '', 'new', 'New lead', 'no answer', 'noanswer', 'no answer 1', 'No_answer_no_answer', 'pending noanswer',
            'callback', 'call back', 'call bаck', 'call again', 'Follow up - callback', 'reshuffle', 'hung up', 'busy',
            'renew', 'na', 'na3', 'initial call', 'in work', 'voicemail', 'voice mail', 'direct vm',
            'no answer wrong number', 'potential', 'high potential', 'potential depositor', 'failed deposit',
            'cc decline', 'payment decline', 'deposit decline',
        ] as $status) {
            yield 'in progress ' . var_export($status, true) => [$status, 'in_progress'];
        }

        foreach ([
            'not interested', 'Not interested - not interested', 'notinterested', 'no interest', 'not intrested',
            'low potential', 'low potetnial', 'no potential', 'not potential', 'zero potential', 'potential low',
            'stopped answering', 'never answer', 'decline', 'no money', 'other company', 'already deposited',
            'deposited with another company', 'dontcallagain', "don't call",
        ] as $status) {
            yield $status => [$status, 'not_interested'];
        }

        foreach ([
            'wrong number', 'Wrong number', 'wrong info', 'unreachable', 'invalidnumber', 'invalid number', 'w number',
            'not reachable', 'worng number', 'wrong number or email', 'wrong phone number', 'wr wrongnumber',
            'wrong number/person', 'wrongnr email', 'call failed', 'check number',
        ] as $status) {
            yield $status => [$status, 'unavailable'];
        }

        foreach ([
            'invalid', 'invalid (language barrier)', 'ivalid language', 'under 18', 'wrong language', 'wrоng language',
            'no language', 'not german', 'no italian', 'na german', 'wrong person', 'Wrong person', 'wrongperson',
            'wrong person / number', 'not registered', 'Cancelled - never registered', "didn't register",
            'wrong geo', 'wrong country', 'wr wrongage', 'fake lead', 'over age', 'under / over age', 'age issue',
            'not valid', 'validation error', 'spam', 'junk lead', 'cross lead', 'fraud', 'duplicate',
        ] as $status) {
            yield $status => [$status, 'invalid_data'];
        }

        foreach (['Completed - telemarketing', 'valid', 'not approved', 'Something new'] as $status) {
            yield $status => [$status, 'unknown'];
        }
    }

    #[DataProvider('statuses')]
    public function testClassifies(?string $raw, string $expected): void
    {
        $mapper = new SaleStatusMapper();
        $mapped = $mapper->map($raw);

        $actual = match (true) {
            $mapper->isUnknown($raw) => 'unknown',
            $mapped === null => 'in_progress',
            $mapped['status'] === 'approved' => 'approved',
            default => (string) $mapped['reason'],
        };

        self::assertSame($expected, $actual);
        self::assertSame($expected === 'in_progress', $mapper->isInProgress($raw));

        if ($mapped !== null && $mapped['status'] === 'rejected') {
            self::assertContains($mapped['reason'], ['unavailable', 'not_interested', 'invalid_data']);
        }
    }

    public function testParserFallsBackToMapperAndKeepsOverrides(): void
    {
        $parser = new PullSpecParser();
        $spec = $parser->hydrateAndValidate([
            'method' => 'GET',
            'path' => '/api/v3/get-leads?api_token={{static.API_TOKEN}}&page={{poll.page}}',
            'items' => 'data',
            'id' => 'id',
            'status' => 'status',
            'status_map' => ['Completed - telemarketing' => ['status' => 'rejected', 'reason' => 'not_interested']],
            'also_approved_when' => ['path' => 'acq', 'eq' => 1],
        ]);
        self::assertNotNull($spec);

        $items = $parser->parse(['data' => [
            ['id' => 1, 'status' => 'No answer', 'acq' => 0],
            ['id' => 2, 'status' => 'Not interested', 'acq' => 0],
            ['id' => 3, 'status' => 'Completed - telemarketing', 'acq' => 0],
            ['id' => 4, 'status' => 'Completed - telemarketing', 'acq' => 1],
            ['id' => 5, 'status' => 'Something new', 'acq' => 0],
        ]], $spec);

        self::assertTrue($items[0]->inProgress);
        self::assertSame(['rejected', 'not_interested'], [$items[1]->status, $items[1]->reason]);
        self::assertSame(['rejected', 'not_interested'], [$items[2]->status, $items[2]->reason]);
        self::assertSame('approved', $items[3]->status);
        self::assertTrue($items[4]->unknown);
    }

    public function testStatusMapIsOptional(): void
    {
        $spec = (new PullSpecParser())->hydrateAndValidate([
            'method' => 'GET',
            'path' => '/leads',
            'items' => 'data',
            'id' => 'id',
            'status' => 'status',
        ]);

        self::assertNotNull($spec);
        self::assertSame([], $spec->statusMap);
    }
}
