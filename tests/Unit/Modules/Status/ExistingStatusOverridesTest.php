<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\Status;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPEssential\Modules\Status\ExistingStatusOverrides;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Auth\Principal;
use WPEssential\Platform\Definitions\InMemoryDefinitionRepository;

final class ExistingStatusOverridesTest extends TestCase
{
    public function testSanitizeKeepsCoreStatusesAndKnownPostTypes(): void
    {
        self::assertSame(
            ['pending' => ['label' => 'In review', 'plural' => 'In review', 'hidden_for' => ['page']], 'draft' => ['label' => 'Idea']],
            ExistingStatusOverrides::sanitize([
                'pending' => ['label' => ' In review ', 'plural' => 'In review', 'hidden_for' => ['page', 'unknown']],
                'draft' => ['label' => 'Idea'],
                'trash' => ['label' => 'Bin'],
                'future' => ['label' => ''],
            ], ['post', 'page']),
        );
    }

    public function testDraftAndPublishedCannotBeHidden(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ExistingStatusOverrides::sanitize(['publish' => ['hidden_for' => ['post']]], ['post']);
    }

    public function testGuardRefusesMovesIntoHiddenStatusOnly(): void
    {
        $overrides = ['pending' => ['hidden_for' => ['page']]];
        $current = static fn (int $id): string => $id === 7 ? 'pending' : 'draft';

        self::assertSame('draft', ExistingStatusOverrides::guard(['post_status' => 'pending', 'post_type' => 'page'], ['ID' => 3], $overrides, $current)['post_status']);
        self::assertSame('draft', ExistingStatusOverrides::guard(['post_status' => 'pending', 'post_type' => 'page'], [], $overrides, $current)['post_status']);
        self::assertSame('pending', ExistingStatusOverrides::guard(['post_status' => 'pending', 'post_type' => 'page'], ['ID' => 7], $overrides, $current)['post_status']);
        self::assertSame('pending', ExistingStatusOverrides::guard(['post_status' => 'pending', 'post_type' => 'post'], ['ID' => 3], $overrides, $current)['post_status']);
    }

    public function testLabelCountMatchesCoreShape(): void
    {
        $count = ExistingStatusOverrides::labelCount('Idea', 'Ideas');
        self::assertSame('Ideas <span class="count">(%s)</span>', $count['plural']);
        self::assertSame($count['singular'], $count[0]);
    }

    public function testSaveThenRevert(): void
    {
        $overrides = new ExistingStatusOverrides(new InMemoryDefinitionRepository());
        $context = new ExecutionContext(new Principal(1), 1);
        $overrides->handle(['statuses' => ['draft' => ['label' => 'Idea']], 'post_types' => ['post']], $context);
        self::assertSame(['draft' => ['label' => 'Idea']], $overrides->overrides());
        $overrides->handle(['revert' => true], $context);
        self::assertSame([], $overrides->overrides());
    }
}
