<?php

declare(strict_types=1);

namespace WPEssential\Tests\Unit\Modules\Emails;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPEssential\Modules\Emails\CoreEmailOverrides;
use WPEssential\Platform\Auth\ExecutionContext;
use WPEssential\Platform\Auth\Principal;
use WPEssential\Platform\Definitions\InMemoryDefinitionRepository;

final class CoreEmailOverridesTest extends TestCase
{
    public function testSanitizeKeepsKnownEmailsOnly(): void
    {
        self::assertSame(
            ['new_user' => ['subject' => 'Welcome', 'off' => true], 'comment_author' => ['body' => "Hi\n{original}"]],
            CoreEmailOverrides::sanitize([
                'new_user' => ['subject' => ' <b>Welcome</b> ', 'body' => '', 'off' => '1'],
                'comment_author' => ['body' => "Hi\r\n{original}"],
                'made_up' => ['subject' => 'x'],
            ]),
        );
    }

    public function testSecurityEmailsStaySendableAndResetKeepsItsLink(): void
    {
        foreach ([['password_reset' => ['off' => '1']], ['email_change' => ['off' => '1']], ['password_reset' => ['body' => 'No link here']]] as $input) {
            try {
                CoreEmailOverrides::sanitize($input);
                self::fail('Expected rejection for ' . json_encode($input));
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        self::assertArrayHasKey('password_reset', CoreEmailOverrides::sanitize(['password_reset' => ['body' => 'Reset: <{reset_link}>']]));
    }

    public function testRenderFillsPlaceholdersAndKeepsCoreTextWhenEmpty(): void
    {
        $core = "Someone asked to reset.\n<https://example.test/wp-login.php?action=rp&key=abc&login=bob>\n";
        self::assertSame($core, CoreEmailOverrides::render('', $core));
        self::assertSame('Go: https://example.test/wp-login.php?action=rp&key=abc&login=bob', CoreEmailOverrides::render('Go: {reset_link}', $core));
        self::assertSame("Note\n" . $core, CoreEmailOverrides::render("Note\n{original}", $core));
    }

    public function testSaveTurnOffAndRevert(): void
    {
        $emails = new CoreEmailOverrides(new InMemoryDefinitionRepository());
        $context = new ExecutionContext(new Principal(1), 1);
        $emails->handle(['emails' => ['new_user' => ['off' => '1', 'subject' => 'Hello']]], $context);
        self::assertTrue($emails->off('new_user'));
        self::assertFalse($emails->off('password_reset'));
        self::assertSame(['subject' => 'Hello', 'message' => 'core'], $emails->filterArray('new_user', ['subject' => 'Core subject', 'message' => 'core']));
        $emails->handle(['revert' => true], $context);
        self::assertFalse($emails->off('new_user'));
    }
}
