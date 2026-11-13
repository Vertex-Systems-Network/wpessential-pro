<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Platform\Admin\Core;

use PHPUnit\Framework\TestCase;
use WPEssential\Platform\Admin\Ui\Button;

final class ProUiToolkitSourceTest extends TestCase
{
    public function testCompletedModuleScreensRenderInsideTheToolkitShell(): void
    {
        $root = dirname(__DIR__, 5);
        foreach (
            [
                'Fields/FieldAdminController.php',
                'Relations/RelationAdminController.php',
                'Query/QueryAdminController.php',
                'AdminColumns/AdminColumnsAdminController.php',
            ] as $controller
        ) {
            $source = file_get_contents($root . '/frameworks/Modules/' . $controller);
            self::assertIsString($source);
            self::assertStringContainsString('class="wrap wpe-ui', $source, $controller);
            self::assertStringContainsString('Shell::topbar( self::PAGE_SLUG )', $source, $controller);
        }
    }
}
