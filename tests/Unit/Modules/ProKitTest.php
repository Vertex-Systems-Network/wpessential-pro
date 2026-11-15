<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use WPEssential\Modules\ProKit\ProKitModule;

final class ProKitTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function testProAddsItsKitFolderAsPro(): void
    {
        $roots = ProKitModule::roots( [ [ 'dir' => '/free/kit' ] ] );
        self::assertCount( 2, $roots );
        self::assertSame( 'pro', $roots[1]['edition'] );
        self::assertSame( 'WPEssential\\Modules\\ProKit\\Elements', $roots[1]['namespace'] );
        self::assertSame( realpath( self::ROOT . '/kit' ), realpath( $roots[1]['dir'] ) );
    }

    public function testEveryProElementHasItsFilesAndWidget(): void
    {
        $dirs = glob( self::ROOT . '/kit/*/element.json' ) ?: [];
        self::assertCount( 17, $dirs );
        foreach ( $dirs as $file ) {
            $name = basename( dirname( $file ) );
            $def  = json_decode( (string) file_get_contents( $file ), true );
            self::assertSame( $name, $def['name'] ?? null );
            self::assertFileExists( dirname( $file ) . '/render.php' );
            $class = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $name ) ) );
            self::assertFileExists( self::ROOT . '/frameworks/Modules/ProKit/Elements/' . $class . '.php' );
        }
    }
}
