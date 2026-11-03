<?php

namespace Base\Field\Behaviour;

use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Omnibridge\Form\FieldBehaviour;

/**
 * The behaviour of each kind, as every host asks it (plan "omnibase
 * agnostic", step F5d): kind => an Omnibridge\Form\FieldBehaviour. The
 * Symfony form types read their options and vars from it, the neutral
 * FormView and FormBinder (Laravel's, React's props) too. A kind without a
 * behaviour is a plain value its kind's generic rule reads (Kind::INPUT_TYPES,
 * FormBinder).
 *
 * An application adds or replaces a kind's behaviour with register(). A
 * field whose host type was named directly (FieldDescriptor::setFormType(),
 * Symfony's ChoiceType for a plain list) has none: its host type decides.
 */
final class Behaviours
{
    /** @var array<string, FieldBehaviour> */
    private static array $behaviours = [];

    private function __construct()
    {
    }

    public static function register(string $kind, FieldBehaviour $behaviour): void
    {
        self::$behaviours[$kind] = $behaviour;
    }

    /** The behaviour of a kind; null for a plain one. */
    public static function of(?string $kind): ?FieldBehaviour
    {
        if (null === $kind) {
            return null;
        }
        if (!isset(self::$behaviours[$kind])) {
            $behaviour = self::default($kind);
            if (null === $behaviour) {
                return null;
            }
            self::$behaviours[$kind] = $behaviour;
        }

        return self::$behaviours[$kind];
    }

    /** The behaviour of a field: its kind's, none when its host type was named directly. */
    public static function ofField(FieldDescriptor $field): ?FieldBehaviour
    {
        return $field->hasExplicitFormType() ? null : self::of($field->getKind());
    }

    /**
     * A field completed with its behaviour's defaults (the options it does
     * not set), as the neutral FormView and FormBinder read it; the field
     * itself when it has no behaviour.
     */
    public static function complete(FieldDescriptor $field): FieldDescriptor
    {
        $behaviour = self::ofField($field);
        if (null === $behaviour) {
            return $field;
        }
        $complete = clone $field;
        $complete->setFormTypeOptions($behaviour->options($field->getFormTypeOptions()));

        return $complete;
    }

    /** @return list<string> the kinds that have a behaviour */
    public static function kinds(): array
    {
        return array_values(array_filter(Kind::all(), static fn (string $kind): bool => null !== self::of($kind)));
    }

    /** Forgets what was registered (tests). */
    public static function reset(): void
    {
        self::$behaviours = [];
    }

    private static function default(string $kind): ?FieldBehaviour
    {
        return match ($kind) {
            Kind::DATETIME_PICKER => new DateTimePicker(),
            Kind::SLUG => new Slug(),
            Kind::BOOLEAN => new Toggle(),
            Kind::COLOR => new JsColor(),
            Kind::COLOR_PICKER => new ColorPickr(),
            Kind::NUMBER => new Number(),
            Kind::PASSWORD => new Password(),
            Kind::CHOICE => new Select(),
            // The enums' values written out: their classes are DBAL types, loaded only when their choices are asked.
            Kind::ROLE => new Select(['capitalize' => true, 'class' => \Base\Enum\UserRole::class, 'empty_data' => 'ROLE_USER']),
            Kind::STATE => new Select(['class' => \Base\Enum\ThreadState::class, 'empty_data' => 'STATE_DRAFT']),
            Kind::GENDER => new Select(['class' => \Base\Enum\UserGender::class, 'empty_data' => 'GENDERLESS']),
            Kind::CURRENCY => new Select(['choices' => array_flip(\Symfony\Component\Intl\Currencies::getNames())]),
            Kind::COUNTRY => new CountrySelect(),
            Kind::ICON => new Icon(),
            Kind::LOCALE => new ChoiceList(Choices::locales(...)),
            // A file sent with its form, its stored files shown (FileType and its children).
            Kind::FILE, Kind::IMAGE, Kind::AVATAR, Kind::AUDIO, Kind::VIDEO => new Upload((string) Upload::variantOf($kind)),
            default => null,
        };
    }
}
