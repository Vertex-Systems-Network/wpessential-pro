<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\CustomTables;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPEssential\Modules\CustomTables\CustomTablesItems;
use WPEssential\Modules\CustomTables\CustomTablesTableBuilder;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Auth\Principal;
use WPEssential\Platform\Definitions\InMemoryDefinitionRepository;

final class CustomTablesItemsTest extends TestCase
{
    private const COLUMNS = [
        ['key' => 'id', 'type' => 'bigint', 'nullable' => false],
        ['key' => 'title', 'type' => 'varchar', 'nullable' => false, 'length' => 10],
        ['key' => 'seats', 'type' => 'integer', 'nullable' => true],
        ['key' => 'price', 'type' => 'decimal', 'nullable' => true],
        ['key' => 'open', 'type' => 'boolean', 'nullable' => false],
        ['key' => 'starts_at', 'type' => 'datetime', 'nullable' => true],
        ['key' => 'meta', 'type' => 'json', 'nullable' => true],
    ];

    public function testValuesFollowColumnTypes(): void
    {
        self::assertSame(
            ['title' => 'Expo', 'seats' => 40, 'price' => '9.50', 'open' => 1, 'starts_at' => '2026-12-01 18:30:00', 'meta' => '{"a":1}'],
            CustomTablesItems::values(self::COLUMNS, ['id' => '99', 'title' => ' Expo ', 'seats' => '40', 'price' => '9.50', 'open' => '1', 'starts_at' => '2026-12-01T18:30', 'meta' => '{"a":1}']),
        );
        self::assertSame(['title' => 'X', 'seats' => null, 'price' => null, 'open' => 0, 'starts_at' => null, 'meta' => null], CustomTablesItems::values(self::COLUMNS, ['title' => 'X']));
    }

    public function testRejectsBadValues(): void
    {
        foreach ([['seats' => '4.5'], ['title' => ''], ['title' => 'far too long title'], ['starts_at' => 'tomorrow'], ['meta' => '{bad'], ['price' => 'abc']] as $bad) {
            try {
                CustomTablesItems::values(self::COLUMNS, $bad + ['title' => 'ok']);
                self::fail('Expected rejection for ' . json_encode($bad));
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testSaveUpdateAndDeleteUseTheManagedTable(): void
    {
        $repository = new InMemoryDefinitionRepository();
        $context = new ExecutionContext(new Principal(1), 1);
        $tableId = (new CustomTablesTableBuilder($repository))->handle(['key' => 'events', 'label' => 'Events', 'columns' => [['key' => 'title', 'type' => 'varchar', 'required' => '1']]], $context)['id'];
        $db = new class {
            public array $calls = [];
            public int $insert_id = 7;
            public function insert($table, $data, $format) { $this->calls[] = ['insert', $table, $data]; return 1; }
            public function update($table, $data, $where, $format, $whereFormat) { $this->calls[] = ['update', $table, $data, $where]; return 1; }
            public function delete($table, $where, $format) { $this->calls[] = ['delete', $table, $where]; return 1; }
        };
        $items = new CustomTablesItems($repository, $db, 'wp_');

        self::assertSame(7, $items->handle(['definition_id' => $tableId, 'operation' => 'save', 'values' => ['title' => 'Expo']], $context)['id']);
        $items->handle(['definition_id' => $tableId, 'operation' => 'save', 'id' => 7, 'values' => ['title' => 'Fair']], $context);
        $items->handle(['definition_id' => $tableId, 'operation' => 'delete', 'id' => 7], $context);

        self::assertMatchesRegularExpression('/^wp_wpe_ct_[0-9a-f]+$/', $db->calls[0][1]);
        self::assertSame(['title' => 'Expo'], $db->calls[0][2]);
        self::assertSame(['id' => 7], $db->calls[1][3]);
        self::assertSame(['delete', $db->calls[0][1], ['id' => 7]], $db->calls[2]);

        $this->expectException(InvalidArgumentException::class);
        $items->handle(['definition_id' => 'not-a-table', 'operation' => 'save'], $context);
    }
}
