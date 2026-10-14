<?php

namespace Tests\Base\Field;

use Base\Field\Type\SelectType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The separators (a space, a comma, a semicolon) cut what is typed into new
 * choices: a select that takes tags gets them, one that does not gets none.
 * select2's tokenizer, given separators without tags, threw at the first
 * space typed in the search ("_removeOldTags is not a function"): a teacher
 * looking for "Cours élémentaire" in a picker found nothing.
 */
class SelectTypeTokenSeparatorsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/');
        $request->setLocale('en');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    /** @return array<string, mixed> the options select2 receives */
    private function select2(array $options): array
    {
        $form = static::getContainer()->get('form.factory')->createNamedBuilder('record', FormType::class, null, ['csrf_protection' => false])
            ->add('picked', SelectType::class, $options + ['mapped' => false, 'required' => false])
            ->getForm();

        return json_decode($form->createView()['picked']->vars['select2'], true);
    }

    public function testAPickerWithoutTagsHasNoSeparators(): void
    {
        $select2 = $this->select2(['choices' => ['Cours préparatoire' => 'cp', 'Cours élémentaire 1' => 'ce1'], 'multiple' => true]);

        $this->assertArrayNotHasKey('tokenSeparators', $select2);
    }

    public function testASelectOfTagsKeepsThem(): void
    {
        $select2 = $this->select2(['tags' => true, 'multiple' => true, 'choices' => []]);

        $this->assertSame([' ', ',', ';'], $select2['tokenSeparators'] ?? null);
    }
}
