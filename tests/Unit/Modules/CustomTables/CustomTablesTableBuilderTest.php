<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\CustomTables;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPEssential\Modules\CustomTables\CustomTablesTableBuilder;
use WPEssential\Modules\CustomTables\Definition\TableDefinitionCompiler;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Auth\Principal;
use WPEssential\Platform\Definitions\InMemoryDefinitionRepository;

final class CustomTablesTableBuilderTest extends TestCase
{
    public function testCreatesCompilableTableWithIdPrimaryKey(): void
    {
        $repository = new InMemoryDefinitionRepository();
        $builder = new CustomTablesTableBuilder($repository);
        $result = $builder->handle([
            'key' => 'events',
            'label' => 'Events',
            'columns' => [
                ['key' => 'title', 'type' => 'varchar', 'required' => '1', 'length' => '120'],
                ['key' => 'price', 'type' => 'decimal'],
                ['key' => '', 'type' => 'text'],
            ],
            'indexed' => ['title'],
        ], $this->context());

        $definition = $repository->get($result['id']);
        self::assertNotNull($definition);
        $schema = (new TableDefinitionCompiler())->compile($definition);
        self::assertSame(['id'], $schema->primaryKey);
        self::assertSame(['id', 'title', 'price'], array_map(static fn ($column) => $column->key, $schema->columns));
        self::assertSame(120, $schema->columns[1]->length);
        self::assertFalse($schema->columns[1]->nullable);
        self::assertSame(10, $schema->columns[2]->precision);
        self::assertSame('idx_title', $schema->indexes[0]->key);
    }

    public function testEditingAddsColumnsAndKeepsSavedOnes(): void
    {
        $repository = new InMemoryDefinitionRepository();
        $builder = new CustomTablesTableBuilder($repository);
        $id = $builder->handle(['key' => 'events', 'label' => 'Events', 'columns' => [['key' => 'title', 'type' => 'varchar']]], $this->context())['id'];

        $builder->handle(['id' => $id, 'key' => 'ignored', 'label' => 'Events', 'columns' => [['key' => 'title', 'type' => 'integer'], ['key' => 'starts_at', 'type' => 'datetime']]], $this->context());

        $payload = $repository->get($id)->payload;
        self::assertSame('events', $payload['key']);
        self::assertSame(['id', 'title', 'starts_at'], array_column($payload['columns'], 'key'));
        self::assertSame('varchar', $payload['columns'][1]['type']);
        self::assertSame(2, $repository->get($id)->revision);
    }

    public function testRejectsDuplicateKeysAndBadColumns(): void
    {
        $repository = new InMemoryDefinitionRepository();
        $builder = new CustomTablesTableBuilder($repository);
        $builder->handle(['key' => 'events', 'label' => 'Events', 'columns' => []], $this->context());

        foreach ([
            ['key' => 'events', 'label' => 'Again', 'columns' => []],
            ['key' => 'bad', 'label' => 'Bad', 'columns' => [['key' => 'id', 'type' => 'integer']]],
            ['key' => 'bad', 'label' => 'Bad', 'columns' => [['key' => 'x', 'type' => 'blob']]],
            ['key' => 'Bad Key', 'label' => 'Bad', 'columns' => []],
        ] as $input) {
            try {
                $builder->handle($input, $this->context());
                self::fail('Expected rejection for ' . json_encode($input));
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    private function context(): ExecutionContext
    {
        return new ExecutionContext(new Principal(1), 1);
    }
}
