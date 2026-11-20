<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\Fields;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPEssential\Modules\Fields\FieldConditionalLogic;
use WPEssential\Modules\Fields\FieldDefinitionNormalizer;

final class FieldConditionalLogicTest extends TestCase
{
    public function testOrGroupsOfAndRules(): void
    {
        $logic = FieldConditionalLogic::normalize([
            [['field' => 'has_event', 'operator' => '==', 'value' => '1'], ['field' => 'city', 'operator' => 'not_empty']],
            [['field' => 'tags', 'operator' => 'contains', 'value' => 'vip']],
        ]);

        self::assertTrue(FieldConditionalLogic::visible($logic, ['has_event' => true, 'city' => 'Lahore']));
        self::assertFalse(FieldConditionalLogic::visible($logic, ['has_event' => true, 'city' => '']));
        self::assertTrue(FieldConditionalLogic::visible($logic, ['tags' => ['guest', 'vip-gold']]));
        self::assertFalse(FieldConditionalLogic::visible($logic, []));
        self::assertTrue(FieldConditionalLogic::visible([], []));
        self::assertTrue(FieldConditionalLogic::visible(FieldConditionalLogic::normalize([[['field' => 'size', 'operator' => '!=', 'value' => 's']]]), ['size' => 'l']));
        self::assertTrue(FieldConditionalLogic::visible(FieldConditionalLogic::normalize([[['field' => 'n', 'operator' => 'empty']]]), ['n' => 0]));
    }

    public function testRejectsBadShapes(): void
    {
        foreach ([[['field' => 'x']], [[['field' => 'x', 'operator' => 'regex']]], [[['field' => 'Bad Key', 'operator' => '==']]], [[]]] as $bad) {
            try {
                FieldConditionalLogic::normalize($bad);
                self::fail('Expected rejection for ' . json_encode($bad));
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testDefinitionNormalizerKeepsValidatedLogic(): void
    {
        $field = (new FieldDefinitionNormalizer())->normalize([
            'key' => 'venue',
            'label' => 'Venue',
            'type' => 'text',
            'settings' => ['conditional_logic' => [[['field' => 'has_event', 'operator' => '==', 'value' => 1]]]],
        ]);
        self::assertSame([[['field' => 'has_event', 'operator' => '==', 'value' => '1']]], $field['settings']['conditional_logic']);

        $empty = (new FieldDefinitionNormalizer())->normalize(['key' => 'a', 'label' => 'A', 'type' => 'text', 'settings' => ['conditional_logic' => []]]);
        self::assertArrayNotHasKey('conditional_logic', $empty['settings']);
    }
}
