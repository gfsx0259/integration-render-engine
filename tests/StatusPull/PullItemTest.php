<?php

declare(strict_types=1);

namespace Enthusiast\IntegrationRenderEngine\Tests\StatusPull;

use Enthusiast\IntegrationRenderEngine\StatusPull\PullItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PullItemTest extends TestCase
{
    /**
     * @param array{deposit?: bool, inProgress?: bool, unknown?: bool, status?: ?string, reason?: ?string} $row
     */
    #[DataProvider('matrix')]
    public function testForPublishMatchesCallbackMatrix(
        array $row,
        bool $depositBySignal,
        ?string $publishStatus,
        bool $callback,
    ): void {
        $item = new PullItem(
            'pub-1',
            $row['raw'] ?? 'raw',
            inProgress: $row['inProgress'] ?? false,
            unknown: $row['unknown'] ?? false,
            status: $row['status'] ?? null,
            reason: $row['reason'] ?? null,
            updatedAt: null,
            deposit: $row['deposit'] ?? false,
        );

        $published = $item->forPublish($depositBySignal);

        self::assertSame($publishStatus, $published->status);
        self::assertSame($callback, $published->isCallback());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool, 2: ?string, 3: bool}>
     */
    public static function matrix(): array
    {
        return [
            'rejected without deposit' => [
                ['status' => 'rejected', 'reason' => 'not_interested'],
                true,
                'rejected',
                true,
            ],
            'mapped approved on FTD adapter, no deposit' => [
                ['status' => 'approved'],
                true,
                null,
                false,
            ],
            'mapped approved on classic adapter' => [
                ['status' => 'approved'],
                false,
                'approved',
                true,
            ],
            'in progress / no answer' => [
                ['inProgress' => true],
                true,
                null,
                false,
            ],
            'unknown raw' => [
                ['unknown' => true],
                true,
                null,
                false,
            ],
            'also_approved_when matched, call status in progress' => [
                ['deposit' => true, 'inProgress' => true, 'raw' => 'no_answer'],
                true,
                'approved',
                true,
            ],
            'also_approved_when matched, call status rejected' => [
                ['deposit' => true, 'status' => 'rejected', 'reason' => 'not_interested', 'raw' => 'not_interested'],
                true,
                'approved',
                true,
            ],
            'deposit list row' => [
                ['deposit' => true, 'inProgress' => true],
                false,
                'approved',
                true,
            ],
        ];
    }
}
