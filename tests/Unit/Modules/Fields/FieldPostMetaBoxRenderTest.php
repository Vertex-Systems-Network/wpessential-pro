<?php

declare(strict_types=1);

namespace {
    // Plain stand-ins for the WordPress output helpers the row renderer calls.
    if (!function_exists('esc_attr')) {
        function esc_attr($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }
    }
    if (!function_exists('esc_html')) {
        function esc_html($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }
    }
    if (!function_exists('esc_textarea')) {
        function esc_textarea($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }
    }
    if (!function_exists('esc_html__')) {
        function esc_html__($v, $d = null): string { return htmlspecialchars((string) $v, ENT_QUOTES); }
    }
    if (!function_exists('__')) {
        function __($v, $d = null): string { return (string) $v; }
    }
    if (!function_exists('checked')) {
        function checked($a, $b = true, $echo = true): string { return $a == $b ? ' checked="checked"' : ''; }
    }
    if (!function_exists('selected')) {
        function selected($a, $b = true, $echo = true): string { return $a == $b ? ' selected="selected"' : ''; }
    }
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($v) { return json_encode($v); }
    }
}

namespace WPEssential\Tests\Unit\Modules\Fields {
    use PHPUnit\Framework\TestCase;
    use WPEssential\Modules\Fields\FieldPostMetaBox;

    final class FieldPostMetaBoxRenderTest extends TestCase
    {
        public function testChoiceAndContainerFieldsRenderTheirOwnControls(): void
        {
            ob_start();
            FieldPostMetaBox::renderRows([
                ['uuid' => 'u1', 'key' => 'size', 'type' => 'select', 'label' => 'Size', 'settings' => ['choices' => ['s' => 'Small', 'l' => 'Large']]],
                ['uuid' => 'u2', 'key' => 'mode', 'type' => 'radio', 'label' => 'Mode', 'settings' => ['choices' => ['a', 'b']]],
                ['uuid' => 'u3', 'key' => 'stops', 'type' => 'repeater', 'label' => 'Stops', 'settings' => [], 'subfields' => [['key' => 'city', 'type' => 'text', 'label' => 'City']]],
            ], 'wpe_fields[g]', static fn (string $uuid): mixed => ['u1' => 'l', 'u2' => 'b', 'u3' => [['city' => 'Lahore']]][$uuid]);
            $html = (string) ob_get_clean();

            self::assertStringContainsString('<select id="wpe-field-u1" name="wpe_fields[g][u1]"', $html);
            self::assertStringContainsString('<option value="l" selected="selected">Large</option>', $html);
            self::assertStringContainsString('type="radio" name="wpe_fields[g][u2]" value="b" checked="checked"', $html);
            self::assertStringContainsString('name="wpe_fields[g][u3][0][city]" value="Lahore"', $html);
            self::assertStringContainsString('name="wpe_fields[g][u3][1][city]"', $html);
            self::assertStringNotContainsString('type="select"', $html);
            self::assertStringNotContainsString('type="repeater"', $html);
        }
    }
}
