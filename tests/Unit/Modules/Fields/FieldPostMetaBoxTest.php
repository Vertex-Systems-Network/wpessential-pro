<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\Fields;

use PHPUnit\Framework\TestCase;
use WPEssential\Modules\Fields\FieldPostMetaBox;

final class FieldPostMetaBoxTest extends TestCase
{
    public function testFormValuesBecomeWriteValueInput(): void
    {
        self::assertTrue(FieldPostMetaBox::value('checkbox', '1'));
        self::assertFalse(FieldPostMetaBox::value('checkbox', '0'));
        self::assertNull(FieldPostMetaBox::value('text', '   '));
        self::assertSame('hello', FieldPostMetaBox::value('text', ' hello '));
        self::assertSame(3, FieldPostMetaBox::value('number', '3'));
        self::assertSame(2.5, FieldPostMetaBox::value('number', '2.5'));
        self::assertSame('2026-11-19', FieldPostMetaBox::value('date', '2026-11-19'));
        // Without WordPress the site timezone falls back to UTC.
        self::assertSame('2026-11-19T10:30:00+00:00', FieldPostMetaBox::value('datetime', '2026-11-19T10:30'));
    }

    public function testStoredDateTimeIsShownForDatetimeLocal(): void
    {
        self::assertSame('2026-11-19T10:30', FieldPostMetaBox::localDateTime('2026-11-19T10:30:00Z'));
        self::assertSame('', FieldPostMetaBox::localDateTime(null));
        self::assertSame('', FieldPostMetaBox::localDateTime('not a date'));
    }
}
