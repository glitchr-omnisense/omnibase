<?php

/*
 * The neutral form layer (plan "omnibase agnostic", step F5b) where no form library is installed:
 * run by tests/BareTest.php through docker/bin/bare --strict, with Composer's autoloader of
 * var/bare/vendor alone (the core without the framework, Symfony Form, the validator, Doctrine nor
 * Twig). A definition of field descriptors is processed - bound, its token checked, validated
 * through omnibridge's Validation - and its view made; what it returns is compared by BareTest.
 */

use Base\Field\BooleanField;
use Base\Field\CountryField;
use Base\Field\EmailField;
use Base\Field\IntegerField;
use Base\Field\TextareaField;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormProcessor;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;

$tokens = new class implements Tokens {
    public function csrf(string $intent): string
    {
        return 'token:'.$intent;
    }

    public function isValid(string $intent, ?string $token): bool
    {
        return 'token:'.$intent === $token;
    }
};
$validation = new class implements Validation {
    public function validate(mixed $value, array $constraints = [], ?array $groups = null): array
    {
        return [] !== $constraints && (null === $value || '' === $value) ? [new Violation('', 'This value should not be blank.')] : [];
    }
};

$form = FormDefinition::new('comment')
    ->add(EmailField::new('email'))
    ->add(TextareaField::new('content')->addConstraint(new stdClass()))
    ->add(IntegerField::new('rating'))
    ->add(BooleanField::new('notify'))
    ->add(CountryField::new('country'))
    ->addAction('submit', 'comment.submit');
$processor = new FormProcessor($validation, $tokens);

$refused = $processor->handle($form, ['email' => ' ringo@example.com ', 'content' => '', 'rating' => 'five', 'country' => 'FR', '_csrf_token' => 'forged']);
$kept = $processor->handle($form, ['email' => 'ringo@example.com', 'content' => 'Hello', 'rating' => '5', 'notify' => '1', 'country' => 'JP', '_csrf_token' => 'token:comment']);
$view = $refused->createView();

return [
    'refused' => $refused->getErrors(),
    'kept' => $kept->isValid() ? $kept->data : null,
    'view' => [
        'email' => $view['email']->vars['value'],
        'rating' => $view['rating']->vars['value'],
        'content' => $view['content']->vars['errors'],
        'country' => $view['country']->vars['type'],
        'token' => $view['_csrf_token']->vars['full_name'].'='.$view['_csrf_token']->vars['value'],
    ],
];
