<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\Fields;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPEssential\Modules\Fields\FieldDefinitionNormalizer;
use WPEssential\Modules\Fields\FieldTermUserScreens;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Auth\Principal;
use WPEssential\Platform\Definitions\Definition;
use WPEssential\Platform\Definitions\DefinitionStatus;
use WPEssential\Platform\Definitions\InMemoryDefinitionRepository;

final class FieldTermUserScreensTest extends TestCase
{
    public function testTargetsFollowStorageModeAndAnchors(): void
    {
        self::assertNull(FieldTermUserScreens::targets(['storage' => ['mode' => 'native_post_meta'], 'locations' => []]));
        self::assertSame(['kind' => 'term', 'keys' => ['category', 'post_tag']], FieldTermUserScreens::targets([
            'storage' => ['mode' => 'native_term_meta'],
            'locations' => [[['source' => 'taxonomy', 'operator' => 'in', 'value' => ['category', 'post_tag'], 'negate' => false]]],
        ]));
        self::assertSame(['kind' => 'user', 'keys' => ['author']], FieldTermUserScreens::targets([
            'storage' => ['mode' => 'native_user_meta'],
            'locations' => [[['source' => 'user_role', 'operator' => 'equals', 'value' => 'author', 'negate' => false]]],
        ]));
    }

    public function testRejectsUnanchoredOrNegatedPlacement(): void
    {
        foreach ([
            [[['source' => 'post_type', 'operator' => 'equals', 'value' => 'post', 'negate' => false]]],
            [[['source' => 'taxonomy', 'operator' => 'not_equals', 'value' => 'category', 'negate' => false]]],
        ] as $locations) {
            try {
                FieldTermUserScreens::targets(['storage' => ['mode' => 'native_term_meta'], 'locations' => $locations]);
                self::fail('Expected rejection');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testWriteChecksCapabilityAndStoresNormalizedValueUnderFieldKey(): void
    {
        $stored = [];
        $meta = [
            'term.get' => static fn () => '',
            'term.update' => static function (int $id, string $key, mixed $value) use (&$stored): void { $stored[$id][$key] = $value; },
            'term.delete' => static function (int $id, string $key) use (&$stored): void { unset($stored[$id][$key]); },
            'user.get' => static fn () => '',
            'user.update' => static fn () => null,
            'user.delete' => static fn () => null,
        ];
        $repository = new InMemoryDefinitionRepository();
        $field = (new FieldDefinitionNormalizer())->normalize(['uuid' => '11111111-1111-4111-8111-111111111111', 'key' => 'color_code', 'label' => 'Colour', 'type' => 'color']);
        $repository->save(new Definition(
            id: '22222222-2222-4222-8222-222222222222',
            slug: 'term-colours',
            type: 'field_group',
            schemaVersion: 1,
            ownerSurfaceId: 3,
            status: DefinitionStatus::Published,
            payload: [
                'title' => 'Colours',
                'storage' => ['mode' => 'native_term_meta'],
                'locations' => [[['source' => 'taxonomy', 'operator' => 'equals', 'value' => 'category', 'negate' => false]]],
                'fields' => [$field],
            ],
        ));
        $allowed = true;
        $screens = new FieldTermUserScreens($repository, meta: $meta, can: static function () use (&$allowed): bool { return $allowed; });
        $context = new ExecutionContext(new Principal(1), 1);
        $input = ['object_type' => 'term', 'object_id' => 5, 'group_id' => '22222222-2222-4222-8222-222222222222', 'field_uuid' => '11111111-1111-4111-8111-111111111111', 'value' => '#ff0000'];

        $screens->handle($input, $context);
        self::assertSame(['color_code' => '#ff0000'], $stored[5]);

        $screens->handle(['value' => null] + $input, $context);
        self::assertSame([], $stored[5]);

        $allowed = false;
        $this->expectException(InvalidArgumentException::class);
        $screens->handle($input, $context);
    }
}
