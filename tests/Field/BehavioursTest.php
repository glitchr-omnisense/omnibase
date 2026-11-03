<?php

namespace Tests\Base\Field;

use Base\Field\Behaviour\Behaviours;
use Base\Field\Behaviour\ColorPickr;
use Base\Field\Behaviour\DateTimePicker;
use Base\Field\Behaviour\JsColor;
use Base\Field\Behaviour\Number;
use Base\Field\Behaviour\Password;
use Base\Field\Behaviour\Select;
use Base\Field\Behaviour\Slug;
use Base\Field\Conformance\Host;
use Base\Field\Conformance\Outcome;
use Base\Field\Conformance\Sample;
use Base\Field\Conformance\Samples;
use Base\Field\Conformance\Suite;
use Base\Field\Kind;
use Base\Field\Kinds;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormView;
use Omnibridge\Form\Coverage;
use PHPUnit\Framework\TestCase;

/**
 * The neutral behaviours of the rich kinds, the hosts' coverage and the conformance suite (plan
 * "omnibase agnostic", step F5d): what the Symfony types and the generic renderers both read.
 */
class BehavioursTest extends TestCase
{
    private string $locale;

    protected function setUp(): void
    {
        $this->locale = \Locale::getDefault();
        \Locale::setDefault('fr_FR');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->locale);
        Kinds::reset();
        Behaviours::reset();
    }

    public function testEveryKindHasASample(): void
    {
        self::assertSame(Kind::all(), array_keys(Samples::all()));
        self::assertSame(array_unique(Kind::all()), Kind::all());
        self::assertContains(Kind::DATETIME_PICKER, Kind::all());
        // The list a host without PHP reads (lesringostars-react's coverage test).
        self::assertSame(Kind::all(), json_decode((string) file_get_contents(__DIR__.'/../../src/Resources/field-kinds.json'), true));
    }

    public function testADatePickersValueInItsFormat(): void
    {
        $picker = new DateTimePicker();
        $options = $picker->options(['datetimepicker' => ['enableTime' => false]]);

        // The page's language: the host's Locales when it has one (another test's kernel may have left one), else Intl's.
        self::assertSame(['enableTime' => false, 'dateFormat' => 'Y-m-d H:i'], array_diff_key($options['datetimepicker'], ['locale' => true]));
        self::assertSame(['enableTime' => true, 'locale' => 'fr', 'dateFormat' => 'Y-m-d H:i'], DateTimePicker::picker([], 'fr'));
        self::assertSame('2026-11-03 10:30', $picker->transform(new \DateTime('2026-11-03 10:30:12'), $options));
        self::assertSame('2026-12-24 18:00:00', $picker->reverse('2026-12-24 18:00', $options));
        self::assertSame('2026-12-24 00:00:00', $picker->reverse('2026-12-24', $picker->options(['format' => 'yyyy-MM-dd'])));
        $this->expectException(\UnexpectedValueException::class);
        $picker->reverse('demain', $options);
    }

    public function testColoursShownLongKeptShort(): void
    {
        $jscolor = new JsColor();
        $options = $jscolor->options([]);
        self::assertSame('#FF0000FF', $jscolor->transform('#F00', $options));
        self::assertSame('#00000000', $jscolor->transform(null, $options));
        self::assertSame('#0F0', $jscolor->reverse('#00FF00FF', $options));
        self::assertNull($jscolor->reverse('#00000000', $options));

        $pickr = new ColorPickr();
        $options = $pickr->options([]);
        self::assertSame('#FF0000FF', $pickr->transform('#FF0000', $options));
        self::assertSame('#conformance_value', json_decode($pickr->vars($options, '#FF0000FF', 'conformance_value')['attr']['data-color-pickr'], true)['el']);
    }

    public function testANumberInThePagesLanguage(): void
    {
        $number = new Number();
        $options = $number->options([]);
        self::assertSame('48,857', $number->transform(48.856614, $options));
        self::assertSame(2.5, $number->reverse('2,5', $options));
        self::assertSame(1250.0, $number->reverse('12,5 €', $number->options(['suffix' => ' €', 'divisor' => 100])) );
        self::assertSame(['stepUp' => 1, 'stepDown' => 1], array_intersect_key($number->vars($options, '', 'x'), ['stepUp' => 1, 'stepDown' => 1]));
    }

    public function testASlugsSettingsAndAPasswordTypedTwice(): void
    {
        $slug = new Slug();
        $vars = $slug->vars($slug->options(['target' => 'translations.title', 'keep' => '_']), null, 'x');
        self::assertSame(['translations', 'title'], $vars['target']);
        self::assertSame('false', $vars['strict']);

        $password = new Password();
        $options = $password->options([]);
        self::assertSame(' Abbey 1969 ', $password->reverse(['plain' => ' Abbey 1969 ', 'plain_repeater' => ' Abbey 1969 '], $options));
        $this->expectException(\UnexpectedValueException::class);
        $password->reverse(['plain' => 'a', 'plain_repeater' => 'b'], $options);
    }

    public function testSelect2sSettingsAsSelectTypeGivesThem(): void
    {
        $select = new Select();
        $options = $select->options(['choices' => ['A' => 'a'], 'required' => false]);
        $settings = Select::select2($options, false, null, 'fr', 'Pick');

        self::assertSame(['multiple', 'minimumResultsForSearch', 'closeOnSelect', 'selectOnClose', 'containerCssClass', 'dropdownCssClass', 'placeholder', 'multivalue', 'language', 'allowClear', 'maximum', 'tags', 'theme', 'data', 'selected'], array_keys($settings));
        self::assertTrue($settings['allowClear']);
        self::assertSame([], $select->endpoints($options));
        self::assertSame('ux_autocomplete', $select->endpoints($select->options(['class' => self::class]))['search']->route, 'a class without choices is searched remotely');
        self::assertSame('b', $select->reverse(['choice' => 'b'], $options));
    }

    public function testTheBinderReadsThroughTheBehaviours(): void
    {
        $form = FormDefinition::new('f')->setCsrf(false)
            ->add(FormDefinition::field('at', Kind::DATETIME_PICKER))
            ->add(FormDefinition::field('colour', Kind::COLOR))
            ->add(FormDefinition::field('state', Kind::CHOICE)->setFormTypeOption('choices', ['Draft' => 'draft', 'Out' => 'out']));
        $binding = (new FormBinder())->bind($form, ['at' => '2026-12-24 18:00', 'colour' => '#00FF00FF', 'state' => ['choice' => 'out']]);

        self::assertSame([], $binding->violations);
        self::assertSame('2026-12-24 18:00:00', $binding->data['at']->format('Y-m-d H:i:s'));
        self::assertSame('#0F0', $binding->data['colour']);
        self::assertSame('out', $binding->data['state']);

        $view = FormView::of($form, (new FormBinder())->read($form, ['at' => new \DateTime('2026-11-03 10:30'), 'colour' => '#F00']));
        self::assertSame('datetimepicker', $view['at']->vars['widget']);
        self::assertSame('2026-11-03 10:30', $view['at']->vars['value']);
        self::assertSame('form.datetime', $view['at']->vars['webpack_entry']);
        self::assertFalse($view['at']->vars['required'], 'the picker\'s own default');
        self::assertSame('#FF0000FF', $view['colour']->vars['value']);
        self::assertSame('select2', $view['state']->vars['widget']);
        self::assertSame('Draft', json_decode($view['state']->vars['select2'], true)['data'][0]['text']);
    }

    public function testAFieldWhoseHostTypeIsNamedHasNoBehaviour(): void
    {
        $field = FormDefinition::field('c', Kind::CHOICE)->setFormType('Symfony\Component\Form\Extension\Core\Type\ChoiceType');

        self::assertNull(Behaviours::ofField($field));
        self::assertSame($field, Behaviours::complete($field));
    }

    public function testAKindAHostLeavesUndeclaredIsAFault(): void
    {
        Kinds::declare('fake', array_fill_keys(Kind::all(), Coverage::IMPLEMENTED));
        self::assertSame([], Kinds::faults('fake'));

        // An application's kind, drawn by one host only: the others must say what they do of it.
        Kinds::register('fake', ['map_point' => 'MapPointType']);
        self::assertStringContainsString('"map_point" is not declared', Kinds::faults('fake')[0]);
        Kinds::declare('other', array_fill_keys(Kind::all(), Coverage::IMPLEMENTED));
        self::assertCount(1, Kinds::faults('other'));
        Kinds::declare('other', ['map_point' => Coverage::unsupported('no map script here')]);
        self::assertSame([], Kinds::faults('other'));
    }

    public function testTheSuiteFailsAPlainInputAndAnUnsupportedKindDrawnInSilence(): void
    {
        $plain = new class implements Host {
            public function name(): string
            {
                return 'plain';
            }

            public function render(FormDefinition $form, mixed $data): string
            {
                return '<input name="conformance[value]" value="" />';
            }

            public function submit(FormDefinition $form, array $submitted, mixed $data): Outcome
            {
                return new Outcome($submitted, []);
            }
        };
        Kinds::declare('plain', [Kind::DATETIME_PICKER => Coverage::IMPLEMENTED, Kind::SLUG => Coverage::unsupported('none')]);

        $faults = Suite::check($plain, Samples::all()[Kind::DATETIME_PICKER]);
        self::assertStringContainsString('the value "2026-11-03 10:30" is not shown', $faults[0]);
        self::assertStringContainsString('is bound as "2026-12-24 18:00", not "2026-12-24 18:00:00"', $faults[1]);
        self::assertStringContainsString('declares "slug" unsupported (none) but draws it', Suite::check($plain, Samples::all()[Kind::SLUG])[0]);
        self::assertStringContainsString('does not declare the kind "text"', Suite::check($plain, Samples::all()[Kind::TEXT])[0]);
        self::assertNotEmpty(Suite::coverage($plain));
        self::assertSame(Sample::RENDER_ONLY, Samples::all()[Kind::FILE]->bound);
    }
}
