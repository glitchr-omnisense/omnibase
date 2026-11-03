<?php

namespace Base\Field\Conformance;

use Base\Field\Behaviour\Choices;
use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Base\Form\Definition\FormDefinition;

/**
 * A sample of every kind the core names (Kind::all()), as Symfony draws and
 * reads it: the reference every other host conforms to. The page's language
 * is French (fr-FR), the host's test sets it.
 *
 * A select2 kind is sent as its form's `choice` part (name[choice]), as
 * omnibase's SelectType names it on both hosts. The kinds whose value is a
 * record, a file or a list of sub-forms are drawn only (RENDER_ONLY): what
 * they hold needs the host's records or an upload.
 */
final class Samples
{
    private function __construct()
    {
    }

    /** @return array<string, Sample> kind => its sample, for every kind of Kind::all() */
    public static function all(): array
    {
        $samples = [];
        foreach (self::declared() as $sample) {
            $samples[$sample->kind] = $sample;
        }
        $missing = array_diff(Kind::all(), array_keys($samples));
        if ([] !== $missing) {
            throw new \LogicException(sprintf('No conformance sample for the kinds %s: a kind the core names has one.', implode(', ', $missing)));
        }

        return $samples;
    }

    /** @param array<string, mixed> $options */
    public static function field(string $kind, array $options = []): \Closure
    {
        return static fn (): FieldDescriptor => FormDefinition::field(Suite::FIELD, $kind)->setFormTypeOptions($options);
    }

    /** @return list<Sample> */
    private static function declared(): array
    {
        $choices = ['First' => 'a', 'Second' => 'b'];
        $date = static fn (string $at): \DateTime => new \DateTime($at);
        $single = ['widget' => 'single_text'];

        return [
            // Text
            new Sample(Kind::TEXT, self::field(Kind::TEXT), 'Ringo', 'Ringo', '  Paul ', 'Paul'),
            new Sample(Kind::TEXTAREA, self::field(Kind::TEXTAREA), 'Help!', 'Help!', ' Yesterday ', 'Yesterday'),
            new Sample(Kind::EMAIL, self::field(Kind::EMAIL), 'ringo@example.com', 'ringo@example.com', 'paul@example.com', 'paul@example.com'),
            // omnibase's password: typed twice (plain, plain_repeater), its strength checked.
            new Sample(Kind::PASSWORD, self::field(Kind::PASSWORD), null, Sample::NONE, ['plain' => 'Abbey-Road-1969', 'plain_repeater' => 'Abbey-Road-1969'], 'Abbey-Road-1969', ['plain' => 'Abbey-Road-1969', 'plain_repeater' => 'Let-It-Be-1970']),
            new Sample(Kind::URL, self::field(Kind::URL, ['default_protocol' => null]), 'https://example.com', 'https://example.com', 'https://lesringostars.fr', 'https://lesringostars.fr'),
            new Sample(Kind::TEL, self::field(Kind::TEL), '0102030405', '0102030405', '0607080910', '0607080910'),
            new Sample(Kind::SEARCH, self::field(Kind::SEARCH), 'abbey', 'abbey', 'road', 'road'),
            new Sample(Kind::SLUG, self::field(Kind::SLUG), 'les-ringo', 'les-ringo', 'les-ringo-stars', 'les-ringo-stars'),
            new Sample(Kind::CODE, self::field(Kind::CODE), null, Sample::NONE, '123456', '123456'),
            // An Editor.js document kept as JSON, shown as it is; one sent without a block is no text.
            new Sample(Kind::EDITOR, self::field(Kind::EDITOR), '{"time":1,"blocks":[{"id":"a","type":"paragraph","data":{"text":"Help!"}}]}', '{"time":1,"blocks":[{"id":"a","type":"paragraph","data":{"text":"Help!"}}]}', '{"time":2,"blocks":[]}', null),
            new Sample(Kind::WYSIWYG, self::field(Kind::WYSIWYG), '<p>Help!</p>', '<p>Help!</p>', '<p>Yesterday</p>', '<p>Yesterday</p>'),
            new Sample(Kind::HIDDEN, self::field(Kind::HIDDEN), 'x', 'x', 'y', 'y'),

            // Numbers
            new Sample(Kind::INTEGER, self::field(Kind::INTEGER), 42, '42', '7', 7, 'abc'),
            new Sample(Kind::NUMBER, self::field(Kind::NUMBER), 48.856614, '48,857', '2,5', 2.5, 'abc'),
            new Sample(Kind::MONEY, self::field(Kind::MONEY)),
            new Sample(Kind::FOREX, self::field(Kind::FOREX)),
            new Sample(Kind::STOCK, self::field(Kind::STOCK)),
            new Sample(Kind::YEAR, self::field(Kind::YEAR), 1964, '1964', '1965', 1965, 'sixty'),

            // Choices
            new Sample(Kind::BOOLEAN, self::field(Kind::BOOLEAN), true, Sample::NONE, '1', true),
            new Sample(Kind::CHECKBOX, self::field(Kind::CHECKBOX), false, Sample::NONE, '1', true),
            // Symfony's select2 takes a value it does not offer (SelectType adds what is sent to its choices): no refusal asked.
            new Sample(Kind::CHOICE, self::field(Kind::CHOICE, ['choices' => $choices]), 'a', Sample::NONE, ['choice' => 'b'], 'b'),
            new Sample(Kind::COUNTRY, self::field(Kind::COUNTRY), 'FR', Sample::NONE, ['choice' => 'JP'], 'JP'),
            new Sample(Kind::CURRENCY, self::field(Kind::CURRENCY), 'EUR', Sample::NONE, ['choice' => 'JPY'], 'JPY'),
            new Sample(Kind::LOCALE, self::field(Kind::LOCALE), 'fr', Sample::NONE, 'ja', 'ja', 'zz_ZZ'),
            // The enums' kinds with their choices given (an entity's column gives them on a CRUD page).
            new Sample(Kind::GENDER, self::field(Kind::GENDER, ['choices' => Choices::of(\Base\Enum\UserGender::class)]), 'MALE', Sample::NONE, ['choice' => 'FEMALE'], 'FEMALE'),
            new Sample(Kind::ROLE, self::field(Kind::ROLE, ['choices' => Choices::of(\Base\Enum\UserRole::class), 'multiple' => true]), ['ROLE_USER'], Sample::NONE, ['choice' => ['ROLE_ADMIN', 'ROLE_USER']], ['ROLE_ADMIN', 'ROLE_USER']),
            new Sample(Kind::STATE, self::field(Kind::STATE, ['choices' => Choices::of(\Base\Enum\ThreadState::class)]), 'STATE_DRAFT', Sample::NONE, ['choice' => 'STATE_PUBLISH'], 'STATE_PUBLISH'),
            new Sample(Kind::ROUTE, self::field(Kind::ROUTE)),
            new Sample(Kind::ICON, self::field(Kind::ICON), 'fa-solid fa-star', Sample::NONE, ['choice' => 'fa-solid fa-moon'], 'fa-solid fa-moon'),
            new Sample(Kind::EMOJI, self::field(Kind::EMOJI)),
            new Sample(Kind::COLOR, self::field(Kind::COLOR), '#F00', '#FF0000FF', '#00FF00FF', '#0F0'),
            new Sample(Kind::COLOR_PICKER, self::field(Kind::COLOR_PICKER), '#FF0000', '#FF0000FF', '#00FF00', '#00FF00'),
            new Sample(Kind::QUADRANT, self::field(Kind::QUADRANT)),

            // Dates
            new Sample(Kind::DATE, self::field(Kind::DATE, $single), $date('2026-11-03'), '2026-11-03', '2026-12-24', $date('2026-12-24 00:00:00'), '24/12'),
            new Sample(Kind::DATETIME, self::field(Kind::DATETIME, $single), $date('2026-11-03 10:30'), '2026-11-03T10:30', '2026-12-24T18:00', $date('2026-12-24 18:00:00'), 'noon'),
            new Sample(Kind::DATETIME_PICKER, self::field(Kind::DATETIME_PICKER), $date('2026-11-03 10:30'), '2026-11-03 10:30', '2026-12-24 18:00', $date('2026-12-24 18:00:00'), 'demain'),
            new Sample(Kind::TIME, self::field(Kind::TIME, $single), $date('1970-01-01 10:30'), '10:30', '18:15', $date('1970-01-01 18:15:00')),

            // Files, records and structures: drawn
            // An account's picture, as each upload kind draws it.
            new Sample(Kind::FILE, self::field(Kind::FILE), dataClass: \Base\Entity\User::class, name: 'avatar'),
            new Sample(Kind::IMAGE, self::field(Kind::IMAGE), dataClass: \Base\Entity\User::class, name: 'avatar'),
            new Sample(Kind::CROPPER, self::field(Kind::CROPPER), dataClass: \Base\Entity\User::class, name: 'avatar'),
            new Sample(Kind::AVATAR, self::field(Kind::AVATAR), dataClass: \Base\Entity\User::class, name: 'avatar'),
            new Sample(Kind::AUDIO, self::field(Kind::AUDIO), dataClass: \Base\Entity\User::class, name: 'avatar'),
            new Sample(Kind::VIDEO, self::field(Kind::VIDEO), dataClass: \Base\Entity\User::class, name: 'avatar'),
            // A thread's tags, its translations: what a record holds of other records.
            new Sample(Kind::ASSOCIATION, self::field(Kind::ASSOCIATION, ['class' => \Base\Entity\Thread\Tag::class, 'multiple' => true]), dataClass: \Base\Entity\Thread::class, name: 'tags', drawn: false),
            new Sample(Kind::ASSOCIATION_FILE, self::field(Kind::ASSOCIATION_FILE, ['class' => \Base\Entity\Layout\Image::class, 'entity_file' => 'source']), dataClass: \Base\Entity\Layout\ImageCrop::class, name: 'image'),
            // No record of the core holds attributes (an application's account's hyperlinks do).
            new Sample(Kind::ATTRIBUTE, self::field(Kind::ATTRIBUTE), exercised: false),
            // A list of texts, a list of hidden values: each entry shown, the list sent kept in its order.
            new Sample(Kind::ARRAY, self::field(Kind::ARRAY), ['un', 'deux'], ['un', 'deux'], ['trois', 'quatre'], ['trois', 'quatre']),
            new Sample(Kind::COLLECTION, self::field(Kind::COLLECTION, ['allow_add' => true]), ['un', 'deux'], ['un', 'deux'], ['trois', 'quatre', 'cinq'], ['trois', 'quatre', 'cinq']),
            new Sample(Kind::REPEATED, self::field(Kind::REPEATED, ['type' => Kind::TEXT]), null, Sample::NONE, ['first' => 'yeah', 'second' => 'yeah'], 'yeah', ['first' => 'yeah', 'second' => 'no']),
            new Sample(Kind::TRANSLATION, self::field(Kind::TRANSLATION), dataClass: \Base\Entity\Thread::class, name: 'translations'),
            new Sample(Kind::DISCRIMINATOR, self::field(Kind::DISCRIMINATOR)),

            // Actions and markup
            // Actions are a definition's actions; as a field, its kind alone, unmapped.
            new Sample(Kind::SUBMIT, self::field(Kind::SUBMIT)),
            new Sample(Kind::BUTTON, self::field(Kind::BUTTON)),
            new Sample(Kind::HTML, self::field(Kind::HTML, ['html' => '<em>markup</em>']), null, 'markup', drawn: false),
            // A host type no kind names (Symfony's RangeType), no kind set: the escape hatch, a host's own.
            new Sample(Kind::CUSTOM, static fn (): FieldDescriptor => (new FieldDescriptor())->setProperty(Suite::FIELD)->setFormType('Symfony\Component\Form\Extension\Core\Type\RangeType'), '5', '5', '7', '7'),
        ];
    }
}
