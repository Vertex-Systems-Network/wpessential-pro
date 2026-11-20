<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\Fields;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPEssential\Modules\Fields\FieldGroupAbilityHandler;
use WPEssential\Modules\Fields\FieldGroupDefinitionNormalizer;
use WPEssential\Modules\Fields\FieldGroupValidationService;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Auth\Principal;
use WPEssential\Platform\Definitions\InMemoryDefinitionRepository;

final class FieldGroupLifecycleTest extends TestCase
{
    public function testLockBlocksTrashAndTrashRestoreDelete(): void
    {
        $repository = new InMemoryDefinitionRepository();
        $group = $this->run_($repository, FieldGroupAbilityHandler::SAVE, ['payload' => [
            'group_key' => 'box',
            'title' => 'Box',
            'fields' => [['key' => 'sub', 'label' => 'Sub', 'type' => 'text']],
        ]])['definition'];

        $locked = $this->run_($repository, FieldGroupAbilityHandler::LIFECYCLE, ['id' => $group['id'], 'expected_revision' => 1, 'action' => 'lock'])['definition'];
        self::assertTrue($locked['payload']['locked']);
        try {
            $this->run_($repository, FieldGroupAbilityHandler::LIFECYCLE, ['id' => $group['id'], 'expected_revision' => 2, 'action' => 'trash']);
            self::fail('A locked group was trashed.');
        } catch (InvalidArgumentException) {
        }

        $this->run_($repository, FieldGroupAbilityHandler::LIFECYCLE, ['id' => $group['id'], 'expected_revision' => 2, 'action' => 'unlock']);
        $trashed = $this->run_($repository, FieldGroupAbilityHandler::LIFECYCLE, ['id' => $group['id'], 'expected_revision' => 3, 'action' => 'trash'])['definition'];
        self::assertTrue($trashed['payload']['trashed']);
        self::assertArrayNotHasKey('locked', $trashed['payload']);

        $restored = $this->run_($repository, FieldGroupAbilityHandler::LIFECYCLE, ['id' => $group['id'], 'expected_revision' => 4, 'action' => 'restore'])['definition'];
        self::assertArrayNotHasKey('trashed', $restored['payload']);

        $this->expectException(InvalidArgumentException::class);
        $this->run_($repository, FieldGroupAbilityHandler::LIFECYCLE, ['id' => $group['id'], 'expected_revision' => 5, 'action' => 'delete']);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function run_(InMemoryDefinitionRepository $repository, string $action, array $input): array
    {
        $normalizer = new FieldGroupDefinitionNormalizer();
        $handler = new FieldGroupAbilityHandler($repository, $normalizer, new FieldGroupValidationService($repository, $normalizer), $action);

        return $handler->handle($input, new ExecutionContext(new Principal(1), 1));
    }
}
