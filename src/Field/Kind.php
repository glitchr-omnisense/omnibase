<?php

namespace Base\Field;

/**
 * What a field is, whatever renders it: the neutral kind a FieldDescriptor
 * names in place of a form type class. A host maps a kind to its own widget
 * (Kinds: the Symfony form type, a Twig or Blade theme's block, a React
 * component); the generic binder (Base\Form\Definition\FormBinder) reads the
 * kind to turn a submitted string into the value the data keeps.
 *
 * A kind is a plain string: a bundle or an application adds its own
 * ("map_point", "signature") and registers what renders it with Kinds.
 */
final class Kind
{
    // Text
    public const TEXT = 'text';
    public const TEXTAREA = 'textarea';
    public const EMAIL = 'email';
    public const PASSWORD = 'password';
    public const URL = 'url';
    public const TEL = 'tel';
    public const SEARCH = 'search';
    public const SLUG = 'slug';
    public const CODE = 'code';
    public const EDITOR = 'editor';
    public const WYSIWYG = 'wysiwyg';
    public const HIDDEN = 'hidden';

    // Numbers
    public const INTEGER = 'integer';
    public const NUMBER = 'number';
    public const MONEY = 'money';
    public const FOREX = 'forex';
    public const STOCK = 'stock';
    public const YEAR = 'year';

    // Choices
    public const BOOLEAN = 'boolean';
    public const CHECKBOX = 'checkbox';
    public const CHOICE = 'choice';
    public const COUNTRY = 'country';
    public const CURRENCY = 'currency';
    public const LOCALE = 'locale';
    public const GENDER = 'gender';
    public const ROLE = 'role';
    public const STATE = 'state';
    public const ROUTE = 'route';
    public const ICON = 'icon';
    public const EMOJI = 'emoji';
    public const COLOR = 'color';
    public const COLOR_PICKER = 'color_picker';
    public const QUADRANT = 'quadrant';

    // Dates
    public const DATE = 'date';
    public const DATETIME = 'datetime';
    public const DATETIME_PICKER = 'datetime_picker';
    public const TIME = 'time';

    // Files
    public const FILE = 'file';
    public const IMAGE = 'image';
    public const CROPPER = 'cropper';
    public const AVATAR = 'avatar';
    public const AUDIO = 'audio';
    public const VIDEO = 'video';

    // Structures
    public const ASSOCIATION = 'association';
    public const ASSOCIATION_FILE = 'association_file';
    public const ATTRIBUTE = 'attribute';
    public const ARRAY = 'array';
    public const COLLECTION = 'collection';
    /** One value typed twice: its option `type` is the kind of the two inputs, first_options and second_options theirs. */
    public const REPEATED = 'repeated';
    public const TRANSLATION = 'translation';
    public const DISCRIMINATOR = 'discriminator';

    // Actions and markup
    public const SUBMIT = 'submit';
    public const BUTTON = 'button';
    /** Markup given whole (a captcha's widget), printed as it is. */
    public const HTML = 'html';

    /** A field whose host type was named directly (setFormType()): the escape hatch. */
    public const CUSTOM = 'custom';

    /** The kinds a renderer prints as a text input, with their HTML type. */
    public const INPUT_TYPES = [
        self::TEXT => 'text', self::EMAIL => 'email', self::PASSWORD => 'password', self::URL => 'url',
        self::TEL => 'tel', self::SEARCH => 'search', self::SLUG => 'text', self::HIDDEN => 'hidden',
        self::INTEGER => 'number', self::NUMBER => 'text', self::MONEY => 'text', self::FOREX => 'text',
        self::STOCK => 'number', self::YEAR => 'number', self::COLOR => 'color', self::COLOR_PICKER => 'text',
        self::DATE => 'date', self::DATETIME => 'datetime-local', self::DATETIME_PICKER => 'text', self::TIME => 'time',
        self::FILE => 'file', self::IMAGE => 'file', self::CROPPER => 'file', self::AVATAR => 'file',
        self::AUDIO => 'file', self::VIDEO => 'file', self::DISCRIMINATOR => 'hidden', self::ICON => 'text',
        self::EMOJI => 'text',
    ];

    /** The kinds whose value is a choice among a list. */
    public const CHOICE_KINDS = [
        self::CHOICE, self::COUNTRY, self::CURRENCY, self::LOCALE, self::GENDER, self::ROLE, self::STATE,
        self::ROUTE, self::QUADRANT, self::ASSOCIATION,
    ];

    /** The kinds whose value is a file sent with the form. */
    public const FILE_KINDS = [self::FILE, self::IMAGE, self::CROPPER, self::AVATAR, self::AUDIO, self::VIDEO, self::ASSOCIATION_FILE];

    /** The kinds that are not a value of the data: buttons and markup. */
    public const UNMAPPED_KINDS = [self::SUBMIT, self::BUTTON, self::HTML];

    private function __construct()
    {
    }

    /** The HTML input type of a kind printed as an input, null for any other widget. */
    public static function inputType(?string $kind): ?string
    {
        return self::INPUT_TYPES[$kind] ?? null;
    }

    public static function isChoice(?string $kind): bool
    {
        return \in_array($kind, self::CHOICE_KINDS, true);
    }

    public static function isFile(?string $kind): bool
    {
        return \in_array($kind, self::FILE_KINDS, true);
    }
}
