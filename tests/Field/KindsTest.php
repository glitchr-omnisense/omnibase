<?php

namespace Tests\Base\Field;

use Base\Field\FieldDescriptor;
use Base\Field\FileField;
use Base\Field\Kind;
use Base\Field\Kinds;
use Base\Field\TextField;
use PHPUnit\Framework\TestCase;

/**
 * The descriptors name a neutral kind (plan "omnibase agnostic", step F5b),
 * and Symfony's form type of each is the one they named before.
 */
class KindsTest extends TestCase
{
    /** The type each descriptor named itself before F5b (core 1f1c5374). */
    private const BEFORE = [
        'ArrayField' => 'Omnibridge\Symfony\Omnibase\Field\Type\ArrayType',
        'AssociationField' => 'Omnibridge\Symfony\Omnibase\Field\Type\AssociationType',
        'AssociationFileField' => 'Omnibridge\Symfony\Omnibase\Field\Type\AssociationFileType',
        'AttributeField' => 'Omnibridge\Symfony\Omnibase\Field\Type\AttributeType',
        'AudioField' => 'Omnibridge\Symfony\Omnibase\Field\Type\AudioType',
        'AvatarField' => 'Omnibridge\Symfony\Omnibase\Field\Type\AvatarType',
        'BooleanField' => 'Omnibridge\Symfony\Omnibase\Field\Type\BooleanType',
        'CodeField' => 'Omnibridge\Symfony\Omnibase\Field\Type\CodeType',
        'CollectionField' => 'Omnibridge\Symfony\Omnibase\Field\Type\CollectionType',
        'ColorField' => 'Omnibridge\Symfony\Omnibase\Field\Type\ColorType',
        'ColorPickerField' => 'Omnibridge\Symfony\Omnibase\Field\Type\ColorPickerType',
        'CountryField' => 'Omnibridge\Symfony\Omnibase\Field\Type\CountryType',
        'CropperField' => 'Omnibridge\Symfony\Omnibase\Field\Type\ImageType',
        'CurrencyField' => 'Omnibridge\Symfony\Omnibase\Field\Type\CurrencyType',
        'DateField' => 'Symfony\Component\Form\Extension\Core\Type\DateType',
        'DateTimeField' => 'Symfony\Component\Form\Extension\Core\Type\DateTimeType',
        'DateTimePickerField' => 'Omnibridge\Symfony\Omnibase\Field\Type\DateTimePickerType',
        'DiscriminatorField' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',
        'EditorField' => 'Omnibridge\Symfony\Omnibase\Field\Type\EditorType',
        'EmailField' => 'Symfony\Component\Form\Extension\Core\Type\EmailType',
        'EmojiPickerField' => 'Omnibridge\Symfony\Omnibase\Field\Type\EmojiPickerType',
        'FileField' => 'Omnibridge\Symfony\Omnibase\Field\Type\FileType',
        'ForexField' => 'Omnibridge\Symfony\Omnibase\Field\Type\ForexType',
        'GenderField' => 'Omnibridge\Symfony\Omnibase\Field\Type\GenderType',
        'IconField' => 'Omnibridge\Symfony\Omnibase\Field\Type\IconType',
        'IdField' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',
        'ImageField' => 'Omnibridge\Symfony\Omnibase\Field\Type\ImageType',
        'IntegerField' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',
        'LocaleField' => 'Symfony\Component\Form\Extension\Core\Type\LocaleType',
        'MoneyField' => 'Omnibridge\Symfony\Omnibase\Field\Type\MoneyType',
        'NumberField' => 'Omnibridge\Symfony\Omnibase\Field\Type\NumberType',
        'PasswordField' => 'Omnibridge\Symfony\Omnibase\Field\Type\PasswordType',
        'QuadrantField' => 'Omnibridge\Symfony\Omnibase\Field\Type\QuadrantType',
        'RoleField' => 'Omnibridge\Symfony\Omnibase\Field\Type\RoleType',
        'RouteField' => 'Omnibridge\Symfony\Omnibase\Field\Type\RouteType',
        'SelectField' => 'Omnibridge\Symfony\Omnibase\Field\Type\SelectType',
        'SlugField' => 'Omnibridge\Symfony\Omnibase\Field\Type\SlugType',
        'StateField' => 'Omnibridge\Symfony\Omnibase\Field\Type\StateType',
        'StockField' => 'Omnibridge\Symfony\Omnibase\Field\Type\StockType',
        'TextField' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        'TextareaField' => 'Symfony\Component\Form\Extension\Core\Type\TextareaType',
        'TranslationField' => 'Omnibridge\Symfony\Omnibase\Field\Type\TranslationType',
        'VideoField' => 'Omnibridge\Symfony\Omnibase\Field\Type\VideoType',
        'WysiwygField' => 'Omnibridge\Symfony\Omnibase\Field\Type\WysiwygType',
        'YearField' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',
    ];

    protected function tearDown(): void
    {
        Kinds::reset();
    }

    public function testEveryDescriptorNamesAKindAndKeepsItsSymfonyType(): void
    {
        foreach (self::BEFORE as $short => $type) {
            $class = 'Base\Field\\'.$short;
            $descriptor = $class::new('someProperty')->getAsDto();
            $this->assertFalse($descriptor->hasExplicitFormType(), $short.' still names a form type');
            $this->assertNotNull($descriptor->getKind(), $short.' names no kind');
            $this->assertNotSame(Kind::CUSTOM, $descriptor->getKind(), $short);
            $this->assertSame($type, $descriptor->getFormType(), $short);
        }
    }

    public function testEveryDescriptorIsListed(): void
    {
        $listed = array_keys(self::BEFORE);
        foreach (glob(\dirname(__DIR__, 2).'/src/Field/*Field.php') as $file) {
            $this->assertContains(basename($file, '.php'), $listed);
        }
    }

    public function testNoDescriptorNamesASymfonyTypeClass(): void
    {
        foreach (glob(\dirname(__DIR__, 2).'/src/Field/*Field.php') as $file) {
            $this->assertDoesNotMatchRegularExpression('/setFormType\(/', file_get_contents($file), basename($file));
        }
    }

    public function testAnImageFileIsTheImageKind(): void
    {
        $this->assertSame(Kind::IMAGE, FileField::new('picture')->renderAsImage()->getAsDto()->getKind());
    }

    public function testATypeNamedDirectlyIsItsKindOrCustom(): void
    {
        $choice = TextField::new('key')->setFormType('Symfony\Component\Form\Extension\Core\Type\ChoiceType')->getAsDto();
        $this->assertSame(Kind::CHOICE, $choice->getKind());
        $this->assertSame('Symfony\Component\Form\Extension\Core\Type\ChoiceType', $choice->getFormType());

        $custom = (new FieldDescriptor())->setFormType('App\Form\SignatureType');
        $this->assertSame(Kind::CUSTOM, $custom->getKind());
    }

    public function testAHostRegistersItsOwnKinds(): void
    {
        Kinds::register('symfony', ['signature' => 'App\Form\SignatureType']);
        $this->assertSame('App\Form\SignatureType', Kinds::type('signature'));
        $this->assertSame('signature', Kinds::kind('App\Form\SignatureType'));
        $this->assertSame('Symfony\Component\Form\Extension\Core\Type\TextType', Kinds::type(Kind::TEXT), 'the known map is kept');

        Kinds::register('blade', [Kind::TEXT => 'omnibase::form.text']);
        $this->assertSame('omnibase::form.text', Kinds::type(Kind::TEXT, 'blade'));
        $this->assertNull(Kinds::type(Kind::EMAIL, 'blade'));
    }

    public function testConstraintsAndOptionsAreCarried(): void
    {
        $constraint = new \stdClass();
        $descriptor = TextField::new('title')->addConstraint($constraint)->setOption('attr.maxlength', 10)->getAsDto();
        $this->assertSame([$constraint], $descriptor->getConstraints());
        $this->assertSame(10, $descriptor->getOption('attr.maxlength'));
        $this->assertSame(['attr' => ['maxlength' => 10]], $descriptor->getOptions());
    }
}
