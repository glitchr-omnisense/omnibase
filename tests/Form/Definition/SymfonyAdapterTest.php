<?php

namespace Tests\Base\Form\Definition;

use Base\Field\BooleanField;
use Base\Field\EmailField;
use Base\Field\IntegerField;
use Base\Field\TextareaField;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Symfony\Omnibase\Form\DefinitionForms;
use Omnibridge\Validation\Constraint\Length;
use Omnibridge\Validation\Constraint\NotBlank;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * The Symfony adapter of the neutral form layer (plan "omnibase agnostic", step F5b): a definition
 * as a Symfony form, the same names, data and errors the neutral processing gives.
 */
class SymfonyAdapterTest extends TestCase
{
    private function forms(): DefinitionForms
    {
        return new DefinitionForms(Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->addExtension(new CsrfExtension(new CsrfTokenManager()))->getFormFactory());
    }

    private function definition(): FormDefinition
    {
        return FormDefinition::new('comment')
            ->add(EmailField::new('email'))
            ->add(TextareaField::new('content', 'comment.content')->setRequired(true)->addConstraint(new NotBlank(), new Length(min: 3)))
            ->add(IntegerField::new('rating')->setVirtual(true))
            ->setCsrf(false);
    }

    public function testTheDefinitionIsASymfonyForm(): void
    {
        $form = $this->forms()->create($this->definition());

        self::assertSame('comment', $form->getName());
        self::assertSame(['email', 'content', 'rating'], array_keys($form->all()));
        self::assertInstanceOf(TextareaType::class, $form->get('content')->getConfig()->getType()->getInnerType());
        self::assertSame('comment.content', $form->get('content')->getConfig()->getOption('label'));
        self::assertTrue($form->get('content')->getConfig()->getOption('required'));
        self::assertFalse($form->get('rating')->getConfig()->getOption('mapped'));
    }

    public function testKeptConstraintsAreSymfonysAndGiveTheirMessages(): void
    {
        $form = $this->forms()->create($this->definition());
        $constraints = $form->get('content')->getConfig()->getOption('constraints');

        self::assertInstanceOf(Assert\NotBlank::class, $constraints[0]);
        self::assertInstanceOf(Assert\Length::class, $constraints[1]);

        $form->submit(['email' => 'ringo@example.com', 'content' => 'ab', 'rating' => 'five']);
        self::assertSame('This value is too short. It should have 3 characters or more.', $form->get('content')->getErrors()[0]->getMessage());
        self::assertSame('Please enter an integer.', $form->get('rating')->getErrors()[0]->getMessage());
        self::assertSame(['email' => 'ringo@example.com', 'content' => 'ab'], array_intersect_key($form->getData(), ['email' => 1, 'content' => 1]));
    }

    public function testTheKernelsContractsAnswerAndAFormerNameIsTheType(): void
    {
        $definition = FormDefinition::new('x')->add(BooleanField::new('notify'))->setCsrf(false);

        self::assertSame('Base\Field\Type\BooleanType', $definition->get('notify')->getFormType());
        self::assertSame('Omnibridge\Symfony\Omnibase\Field\Type\BooleanType', (new \ReflectionClass($definition->get('notify')->getFormType()))->getName());
    }
}
