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

    public function testComparisonTextAndListOperators(): void
    {
        $rule = static fn (string $operator, string $value): array => FieldConditionalLogic::normalize([[['field' => 'x', 'operator' => $operator, 'value' => $value]]]);
        $cases = [
            ['>', '10', 11, true], ['>', '10', 10, false], ['>=', '10', 10, true], ['<', '10', 9.5, true], ['<=', '10', 11, false],
            ['>', '10', '', false],
            ['starts_with', 'VIP', 'VIP-gold', true], ['ends_with', 'gold', 'VIP-gold', true], ['ends_with', 'gold', 'silver', false],
            ['not_contains', 'spam', 'hello', true], ['not_contains', 'spam', 'spammy', false],
            ['in', 'red, blue', 'blue', true], ['in', 'red, blue', 'green', false], ['not_in', 'red, blue', 'green', true],
            ['in', 'a,b', ['c', 'b'], true],
        ];
        foreach ($cases as [$operator, $value, $current, $expected]) {
            self::assertSame($expected, FieldConditionalLogic::visible($rule($operator, $value), ['x' => $current]), "{$operator} {$value}");
        }
        $this->expectException(InvalidArgumentException::class);
        $rule('>', 'ten');
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
