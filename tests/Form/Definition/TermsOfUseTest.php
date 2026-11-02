<?php

namespace Tests\Base\Form\Definition;

use Base\Form\Definition\AccountForms;
use Base\Form\Definition\FormProcessor;
use Omnibridge\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * The sign-up's terms of use, checked by the server and not only by the browser (plan "omnibase
 * agnostic", step F5c: a sign-up without the box ticked made its account): said on top of the form,
 * in the groups a sign-up validates ("new") as in the default one.
 */
class TermsOfUseTest extends TestCase
{
    public function testASignUpWithoutTheTermsIsRefused(): void
    {
        $form = AccountForms::signUp('sign_up')->setDataClass(null)->setCsrf(false)->setGuard(false);
        $processor = new FormProcessor(new Validator(static fn (): bool => false));
        $sent = ['email' => 'anne@example.org', 'plainPassword' => ['first' => 'A-long-Passphrase-42!', 'second' => 'A-long-Passphrase-42!']];

        foreach ([['new'], null] as $groups) {
            $refused = $processor->handle($form, $sent, groups: $groups);
            self::assertSame(['' => ['user.agree_terms']], $refused->getErrors(), json_encode($groups));
            self::assertTrue($processor->handle($form, $sent + ['agreeTerms' => '1'], groups: $groups)->isValid());
        }
    }
}
