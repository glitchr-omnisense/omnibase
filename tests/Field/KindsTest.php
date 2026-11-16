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
        'ArrayField' => 'Base\Field\Type\ArrayType',
        'AssociationField' => 'Base\Field\Type\AssociationType',
        'AssociationFileField' => 'Base\Field\Type\AssociationFileType',
        'AttributeField' => 'Base\Field\Type\AttributeType',
        'AudioField' => 'Base\Field\Type\AudioType',
        'AvatarField' => 'Base\Field\Type\AvatarType',
        'BooleanField' => 'Base\Field\Type\BooleanType',
        'CodeField' => 'Base\Field\Type\CodeType',
        'CollectionField' => 'Base\Field\Type\CollectionType',
        'ColorField' => 'Base\Field\Type\ColorType',
        'ColorPickerField' => 'Base\Field\Type\ColorPickerType',
        'CountryField' => 'Base\Field\Type\CountryType',
        'CropperField' => 'Base\Field\Type\ImageType',
        'CurrencyField' => 'Base\Field\Type\CurrencyType',
        'DateField' => 'Symfony\Component\Form\Extension\Core\Type\DateType',
        'DateTimeField' => 'Symfony\Component\Form\Extension\Core\Type\DateTimeType',
        'DateTimePickerField' => 'Base\Field\Type\DateTimePickerType',
        'DiscriminatorField' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',
        'EditorField' => 'Base\Field\Type\EditorType',
        'EmailField' => 'Symfony\Component\Form\Extension\Core\Type\EmailType',
        'EmojiPickerField' => 'Base\Field\Type\EmojiPickerType',
        'FileField' => 'Base\Field\Type\FileType',
        'ForexField' => 'Base\Field\Type\ForexType',
        'GenderField' => 'Base\Field\Type\GenderType',
        'IconField' => 'Base\Field\Type\IconType',
        'IdField' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',
        'ImageField' => 'Base\Field\Type\ImageType',
        'IntegerField' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',
        'LocaleField' => 'Symfony\Component\Form\Extension\Core\Type\LocaleType',
        'MoneyField' => 'Base\Field\Type\MoneyType',
        'NumberField' => 'Base\Field\Type\NumberType',
        'PasswordField' => 'Base\Field\Type\PasswordType',
        'QuadrantField' => 'Base\Field\Type\QuadrantType',
        'RoleField' => 'Base\Field\Type\RoleType',
        'RouteField' => 'Base\Field\Type\RouteType',
        'SelectField' => 'Base\Field\Type\SelectType',
        'SlugField' => 'Base\Field\Type\SlugType',
        'StateField' => 'Base\Field\Type\StateType',
        'StockField' => 'Base\Field\Type\StockType',
        'TextField' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        'TextareaField' => 'Symfony\Component\Form\Extension\Core\Type\TextareaType',
        'TranslationField' => 'Base\Field\Type\TranslationType',
        'VideoField' => 'Base\Field\Type\VideoType',
        'WysiwygField' => 'Base\Field\Type\WysiwygType',
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
