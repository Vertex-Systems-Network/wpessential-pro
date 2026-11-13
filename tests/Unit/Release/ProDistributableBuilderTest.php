<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

final class ProDistributableBuilderTest extends TestCase
{
    public function testProBuilderRefusesServerCode(): void
    {
        $builder = (string) file_get_contents(dirname(__DIR__, 3) . '/tools/release/build-pro-distributable.php');
        self::assertStringContainsString("assertNoServerCode(\$proStage, 'Pro');", $builder);
        self::assertStringContainsString('validateProStage($proStage, $proAssets);', $builder);
    }
}
