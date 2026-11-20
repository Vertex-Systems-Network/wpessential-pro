<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\Fields;

use PHPUnit\Framework\TestCase;
use WPEssential\Modules\Fields\FieldLocationMatcher;

final class FieldLocationMatcherTest extends TestCase
{
    public function testSpecificObjectsNarrowTheAnchor(): void
    {
        $locations = [[
            ['source' => 'post_type', 'operator' => 'equals', 'value' => 'page', 'negate' => false],
            ['source' => 'entity_id', 'operator' => 'in', 'value' => ['12', '14'], 'negate' => false],
        ]];

        self::assertTrue(FieldLocationMatcher::matches($locations, ['post_type' => ['page'], 'entity_id' => ['14']]));
        self::assertFalse(FieldLocationMatcher::matches($locations, ['post_type' => ['page'], 'entity_id' => ['13']]));
        self::assertFalse(FieldLocationMatcher::matches($locations, ['post_type' => ['post'], 'entity_id' => ['12']]));
    }

    public function testOrGroupsNegationAndUnknownSources(): void
    {
        $locations = [
            [['source' => 'user_role', 'operator' => 'in', 'value' => ['editor'], 'negate' => false]],
            [
                ['source' => 'user_role', 'operator' => 'equals', 'value' => 'author', 'negate' => false],
                ['source' => 'user', 'operator' => 'in', 'value' => ['5'], 'negate' => true],
                ['source' => 'page_template', 'operator' => 'equals', 'value' => 'x', 'negate' => false],
            ],
        ];

        self::assertTrue(FieldLocationMatcher::matches($locations, ['user_role' => ['editor'], 'user' => ['5']]));
        self::assertTrue(FieldLocationMatcher::matches($locations, ['user_role' => ['author'], 'user' => ['6']]));
        self::assertFalse(FieldLocationMatcher::matches($locations, ['user_role' => ['author'], 'user' => ['5']]));
        self::assertTrue(FieldLocationMatcher::matches([], ['user' => ['1']]));
    }
}
