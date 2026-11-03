<?php

namespace Tests\Base\Field;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractActionAdapter;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Entity\Layout\Attribute\Adapter\HyperpatternAdapter;
use Base\Entity\Layout\Attribute\Common\AbstractAction;
use Base\Entity\Layout\Attribute\Hyperlink;
use Base\Entity\Thread;
use Base\Field\Behaviour\Association;
use Base\Field\Behaviour\Attribute;
use Base\Field\Behaviour\Collection;
use Base\Field\Kind;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormView;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Embedded forms, lists and attributes on every host (plan "omnibase agnostic", step F5d, slice 7):
 * Base\Field\Behaviour\{Association, Collection, Attribute}, what AssociationType, CollectionType, ArrayType and
 * AttributeType decided and now ask, and the neutral FormView and FormBinder drawing and binding them.
 */
final class AssociationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        // The records' shapes (a thread's associations), as a host hands its unit of work to omnibase.
        Runtime::set(UnitOfWork::class, static::getContainer()->get(UnitOfWork::class));
    }

    protected function tearDown(): void
    {
        Runtime::set(UnitOfWork::class, null);
        parent::tearDown();
    }

    public function testAnAssociationsOptionsAreTheTypes(): void
    {
        $several = Association::options(['multiple' => true]);
        self::assertFalse($several['required'], 'several records are never required: no entry is a value');
        self::assertNull($several['data_class']);
        self::assertTrue(Association::options(['multiple' => false])['required'], 'one record: entry_required');
        self::assertFalse(Association::options(['multiple' => false, 'entry_required' => false])['required']);
        self::assertFalse(Association::options(['group' => false, 'allow_add' => true])['allow_add'], 'outside a group, nothing added');
        self::assertNull(Association::options(['allow_add' => null])['allow_add'], 'left to the collection');

        $collection = Association::collection(array_replace(Association::options(['multiple' => true, 'allow_add' => false, 'max_entries' => 10]), ['class' => Thread::class]));
        self::assertFalse($collection['allow_add']);
        self::assertSame(10, $collection['max_entries']);
        self::assertSame(Thread::class, $collection['entry_options']['data_class'], 'each entry one record');
        self::assertFalse($collection['entry_options']['multiple']);
        self::assertArrayNotHasKey('class', $collection['entry_options']);
        self::assertSame(1, Association::collection(array_replace(Association::options(['group' => false]), ['class' => Thread::class]))['length'], 'outside a group, one entry at least');
    }

    public function testAnAssociationIsGuessedFromItsRecord(): void
    {
        self::assertSame(Thread::class, Association::targetClass([], Thread::class, 'children'));
        self::assertTrue(Association::isMultiple([], Thread::class, 'children'));
        self::assertFalse(Association::isMultiple([], Thread::class, 'parent'));
        self::assertSame('given', Association::targetClass(['class' => 'given'], Thread::class, 'children'));

        $fields = Association::fields(Thread::class, Association::options(['autoload' => false, 'fields' => ['slug' => ['form_type' => 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType'], 'state' => ['form_type' => 'Symfony\\Component\\Form\\Extension\\Core\\Type\\HiddenType']]]));
        self::assertSame(['slug', 'state'], array_keys($fields), 'the fields named, in their order: no autoload');
        self::assertSame(Kind::TEXT, $fields['slug']->getKind());
        self::assertSame(Kind::HIDDEN, $fields['state']->getKind());
    }

    public function testAListsOptionsAreTheTypes(): void
    {
        $collection = Collection::options([]);
        self::assertFalse($collection['allow_add']);
        self::assertTrue($collection['allow_delete']);
        self::assertSame(Kind::HIDDEN, $collection['entry_type']);
        self::assertTrue($collection['required']);
        self::assertSame(['block_name' => 'entry', 'label' => false], $collection['entry_options']);

        $array = Collection::options(['pattern' => 'https://{0}/{1}'], Kind::ARRAY);
        self::assertSame(2, $array['length'], 'a pattern\'s arguments counted');
        self::assertFalse($array['allow_add'], 'a fixed length neither adds');
        self::assertFalse($array['allow_delete'], 'nor deletes');
        self::assertSame('form.array', $array['webpack_entry']);
        self::assertTrue(Collection::options([], Kind::ARRAY)['allow_add']);
        $this->expectException(\InvalidArgumentException::class);
        Collection::options(['pattern' => '{0}', 'target' => 'x'], Kind::ARRAY);
    }

    public function testAListsWindowAndWhatIsSent(): void
    {
        $entries = range(0, 24);
        self::assertSame([10 => 10, 11 => 11], Collection::window($entries, ['max_entries' => 2, 'entry_offset' => 10]), 'a capped list\'s window, its indexes kept');
        self::assertSame($entries, Collection::window($entries, ['max_entries' => null]));
        self::assertSame([0 => true, 3 => true], Collection::rendered('0, 3,x'));
        self::assertSame(['a', 'b'], Collection::entries(['a', 'b', '_rendered' => '0,1'], []), 'the indexes drawn left out');
        self::assertSame(['k' => 'v'], Collection::entries([['key' => 'k', 'value' => 'v'], ['key' => '', 'value' => 'w']], ['associative' => true]));
        self::assertSame([['key' => 'k', 'value' => 'v']], Collection::shown(['k' => 'v'], ['associative' => true]));
        self::assertStringContainsString('collection-empty', Collection::empty());
    }

    public function testAListIsDrawnAndBoundOnTheNeutralSide(): void
    {
        $form = FormDefinition::new('crud_form')->setCsrf(false)->add(FormDefinition::field('roles', Kind::ARRAY));
        $view = FormView::of($form, ['roles' => ['ROLE_EDITOR', 'ROLE_ADMIN']]);
        self::assertSame(['0', '1'], array_map('strval', array_keys($view['roles']->children)), 'one entry per role');
        self::assertSame('ROLE_ADMIN', $view['roles']['1']->vars['value']);
        self::assertSame('crud_form[roles][1]', $view['roles']['1']->vars['full_name']);
        self::assertSame('crud_form[roles][__prototype__]', $view['roles']->vars['prototype']->vars['full_name'], 'the prototype of a new one');
        self::assertSame('array', $view['roles']->vars['widget']);

        $binding = (new FormBinder())->bind($form, ['roles' => ['ROLE_USER', '', 'ROLE_ADMIN']]);
        self::assertSame(['ROLE_USER', '', 'ROLE_ADMIN'], $binding->data['roles']);
    }

    public function testAnAttributesClassesAndSelect(): void
    {
        self::assertSame(['class' => Hyperlink::class, 'abstract_class' => AbstractAdapter::class], Attribute::classes([], Hyperlink::class));
        self::assertSame(AbstractActionAdapter::class, Attribute::classes(['class' => AbstractAction::class])['abstract_class'], 'an action\'s adapters');
        self::assertSame(\Base\Entity\Layout\Attribute::class, Attribute::classes([], \stdClass::class)['class'], 'not an attribute: Attribute');

        $choice = Attribute::choice(array_replace(Attribute::options(['filter' => [HyperpatternAdapter::class], 'multiple' => true]), ['abstract_class' => AbstractAdapter::class]), new Thread());
        self::assertSame(AbstractAdapter::class, $choice['class']);
        self::assertSame([HyperpatternAdapter::class], $choice['choice_filter']);
        self::assertSame(['code' => null], $choice['autocomplete_fields']);
        self::assertTrue($choice['disable'], 'a record not stored: no attribute chosen yet');
        self::assertSame('field-attribute-dropdown', $choice['dropdownCssClass']);
        self::assertFalse($choice['href']);

        $hyperlink = new Hyperlink(new HyperpatternAdapter('Site', 'website'));
        self::assertSame('website-', Attribute::key($hyperlink), 'its adapter\'s code and its id');
        self::assertTrue(Attribute::translated($hyperlink));
        self::assertSame([$hyperlink], Attribute::held([$hyperlink, 'x'], true));
    }
}
