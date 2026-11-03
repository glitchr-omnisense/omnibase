<?php

namespace Base\Form\Definition;

use Base\Field\Behaviour\Behaviours;
use Base\Field\Behaviour\Guess;
use Base\Field\Behaviour\Translation;
use Base\Field\Behaviour\Upload;
use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Base\Field\Search\RemoteSearch;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Urls;
use Omnibridge\Form\Endpoint;
use Omnibridge\Runtime;

/**
 * A form as a renderer reads it, neutral: a plain tree of views, each with
 * its vars - name, full_name, id, kind, type (the input's HTML type), value,
 * label and help (translatable keys), errors, attr, required, disabled,
 * choices, multiple, checked - and its children by name. The root carries the
 * method, the action and the token (a hidden child under
 * FormDefinition::TOKEN_FIELD).
 *
 * Its vars are named as Symfony's FormView names them, so a theme written for
 * either reads both: the Twig theme (omnibridge/twig), the Blade theme
 * (omnibridge/laravel), and React through toArray() (the page's props).
 *
 * A rich kind's field also carries what its behaviour gives
 * (Base\Field\Behaviour\Behaviours, the vars omnibase's Symfony type gives
 * its block): `widget`, the block its markup is (datetimepicker, slug,
 * select2...), `webpack_entry`, its settings (datetimepicker, select2...,
 * data attributes in attr), and `endpoints`, the address of each route it
 * calls on this host (null where the host has no such route).
 *
 * @implements \ArrayAccess<string, FormView>
 * @implements \IteratorAggregate<string, FormView>
 */
final class FormView implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable
{
    /** @var array<string, mixed> */
    public array $vars;

    /** @var array<string, FormView> */
    public array $children = [];

    public bool $rendered = false;

    /** @param array<string, mixed> $vars */
    public function __construct(string $name, array $vars = [], public readonly ?FormView $parent = null)
    {
        $full = null !== $parent ? $parent->vars['full_name'].'['.$name.']' : $name;
        // Symfony's ids: the full name's brackets made underscores, leading underscores and digits dropped.
        $id = null !== $parent ? $parent->vars['id'].'_'.$name : ltrim($name, '_0123456789');
        $this->vars = $vars + [
            'name' => $name, 'full_name' => $full, 'id' => $id, 'kind' => Kind::TEXT, 'type' => 'text',
            'value' => null, 'label' => null, 'help' => null, 'errors' => [], 'attr' => [], 'row_attr' => [],
            'required' => false, 'disabled' => false, 'translation_domain' => $parent?->vars['translation_domain'] ?? null,
        ];
    }

    /**
     * The view of a definition: one child per field (with the values shown
     * and their errors), one per action, the token's hidden child.
     *
     * @param array<string, mixed>        $values     by field name (FormBinder::read() or Binding::$values)
     * @param array<string, list<string>> $errors     by field name, "" for the form's own
     * @param mixed                       $data       the data the form edits: a file field shows the record's stored files
     */
    public static function of(FormDefinition $form, array $values = [], array $errors = [], ?string $token = null, bool $submitted = false, mixed $data = null): self
    {
        $root = new self($form->getName(), [
            'kind' => 'form', 'type' => 'form', 'method' => $form->getMethod(), 'action' => $form->getTarget() ?? '',
            'attr' => $form->getAttr(), 'errors' => array_values($errors[''] ?? []), 'submitted' => $submitted,
            'valid' => [] === array_filter($errors), 'translation_domain' => $form->getTranslationDomain(),
            'groups' => $form->getGroups(), 'multipart' => self::isMultipart($form),
        ]);

        foreach ($form->getFields() as $name => $field) {
            $field = \Base\Field\Behaviour\Guess::from($field, $form->getDataClass());
            if (Behaviours::ofField($field) instanceof Upload) {
                $field = self::uploaded($field, $name, $data, $values[$name] ?? null);
            }
            $root->children[$name] = self::field($root, $name, $field, $values[$name] ?? null, $errors[$name] ?? []);
        }
        foreach ($root->children as $child) {
            if ('slug' === ($child->vars['widget'] ?? null)) {
                $child->vars['target_id'] = self::target($root, $child->vars['target'] ?? null);
            }
        }
        foreach ($form->getActions() as $name => $action) {
            $root->children[$name] = new self($name, [
                'kind' => $action['kind'], 'type' => $action['options']['type'] ?? (Kind::BUTTON === $action['kind'] ? 'button' : 'submit'),
                'label' => $action['label'], 'attr' => $action['attr'],
                'confirmation' => (bool) ($action['options']['confirmation'] ?? false),
                'button' => Kind::BUTTON === $action['kind'],
            ], $root);
        }
        if ($form->hasCsrf() && null !== $token) {
            $root->children[FormDefinition::TOKEN_FIELD] = new self(FormDefinition::TOKEN_FIELD, [
                'kind' => Kind::HIDDEN, 'type' => 'hidden', 'value' => $token, 'attr' => ['data-controller' => 'csrf-protection'],
            ], $root);
        }

        return $root;
    }

    /**
     * A file field told what its block reads (Upload::vars()): the record it edits, its property, its
     * value where the data is no record (a form's model).
     */
    private static function uploaded(FieldDescriptor $field, string $name, mixed $data, mixed $value): FieldDescriptor
    {
        $property = (string) ($field->getOption('property_path') ?? $field->getProperty() ?? $name);
        if (null === $value && null !== $data && false !== $field->getOption('mapped') && Properties::isReadable($data, $property)) {
            $value = Properties::get($data, $property);
        }

        return (clone $field)->setFormTypeOptions($field->getFormTypeOptions() + [
            'upload_record' => \is_object($data) ? $data : null, 'upload_property' => $name, 'upload_data' => $value,
        ]);
    }

    /** @param list<string> $errors */
    private static function field(self $root, string $name, FieldDescriptor $field, mixed $value, array $errors): self
    {
        $behaviour = Behaviours::ofField($field);
        $field = Behaviours::complete($field);
        $kind = (string) $field->getKind();
        $multiple = true === $field->getOption('multiple');
        $attr = array_replace($field->getHtmlAttributes(), (array) $field->getOption('attr', []));
        if (null !== $placeholder = $field->getOption('placeholder')) {
            $attr['placeholder'] ??= $placeholder;
        }
        $label = $field->getLabel() ?? $field->getOption('label');

        $vars = [
            'kind' => $kind,
            'type' => self::type($kind, $field),
            'value' => $value,
            'label' => false === $label ? false : (null !== $label ? (string) $label : null),
            'help' => null !== ($help = $field->getHelp() ?? $field->getOption('help')) ? (string) $help : null,
            'errors' => array_values($errors),
            'attr' => $attr,
            'row_attr' => (array) $field->getOption('row_attr', []),
            // As Symfony's forms: a field is required unless it says otherwise, a hidden one never.
            'required' => Kind::HIDDEN !== $kind && (bool) ($field->isRequired() ?? $field->getOption('required') ?? true),
            'disabled' => (bool) ($field->isDisabled() ?? $field->getOption('disabled') ?? false),
            'multiple' => $multiple,
            'expanded' => true === $field->getOption('expanded'),
            'label_translation_parameters' => $field->getTranslationParameters(),
            // A field that names its host type (setFormType(), the escape hatch): no behaviour, its generic view only.
            'explicit' => $field->hasExplicitFormType(),
            'translation_domain' => $field->getOption('translation_domain') ?? $root->vars['translation_domain'],
        ];
        if (Kind::SUBMIT === $kind || Kind::BUTTON === $kind) {
            // A button given as a field: omnibase's button2, as Symfony's SubmitType and ButtonType draw it.
            $vars['button'] = true;
            $vars['confirmation'] = (bool) $field->getOption('confirmation');
            $vars['required'] = false;
        }
        if (Kind::HTML === $kind) {
            $vars['html'] = $field->getOption('html'); // the markup given whole, printed as it is
        }
        if (Kind::BOOLEAN === $kind || Kind::CHECKBOX === $kind) {
            $vars['checked'] = (bool) $value;
            $vars['value'] = (string) ($field->getOption('value') ?? '1');
        }
        if (null !== $field->getOption('choices')) {
            $vars['choices'] = self::choices(FormBinder::choices($field) ?? [], $field);
            $vars['placeholder'] = $field->getOption('placeholder');
        }
        if (null !== $behaviour) {
            $vars = self::behave($vars, $behaviour, $field, $value, $root->vars['id'].'_'.$name);
        }

        $view = new self($name, $vars, $root);
        if (Kind::TRANSLATION === $kind && !$field->hasExplicitFormType()) {
            self::translations($view, $field, $value);
        }
        if (Kind::REPEATED === $kind) {
            $inner = (string) ($field->getOption('type') ?? Kind::TEXT);
            $errored = $errors;
            foreach (['first', 'second'] as $part) {
                $child = $field->getOption($part.'_name') ?? $part;
                $view->children[$child] = new self($child, [
                    'kind' => $inner, 'type' => Kind::inputType($inner) ?? 'text', 'value' => null, 'required' => $vars['required'],
                    'attr' => (array) (($field->getOption($part.'_options') ?? [])['attr'] ?? []),
                    'label' => (($field->getOption($part.'_options') ?? [])['label'] ?? null),
                    'errors' => $errored,
                ], $view);
                $errored = [];
            }
            $view->vars['errors'] = [];
        }

        return $view;
    }

    /**
     * A translations field's parts, as TranslationType builds them (Translation): one per language drawn,
     * named by it, each the translation entity's fields with the values the record holds in that language
     * (FormBinder: language => field => value). A field is required where it, its language's part and the
     * translations field are (Symfony's forms: a field inside a part not required is not). Its vars are
     * TranslationType's: locale, single_locale, default_locale, available_locales, required_locales,
     * nonempty_locales, compatible_locale (the tab shown first), translations_empty.
     */
    private static function translations(self $view, FieldDescriptor $field, mixed $value): void
    {
        $options = Translation::options($field->getFormTypeOptions());
        $class = Translation::translationClass($options, null);
        $values = \is_array($value) ? $value : [];
        $fields = null !== $class ? Translation::fields($class, $options, array_map('strval', array_keys($values))) : [];
        ['default' => $default] = Translation::locales($options, array_map('strval', array_keys($values)));

        $nonempty = [];
        foreach ($fields as $locale => $descriptors) {
            $entry = Translation::entryRequired($locale, $default, $options) ?? true;
            $part = new self($locale, ['kind' => 'form', 'type' => 'form', 'label' => false, 'required' => $entry && $view->vars['required']], $view);
            foreach ($descriptors as $name => $descriptor) {
                $child = self::field($part, $name, Guess::from($descriptor, $class), $values[$locale][$name] ?? null, []);
                $child->vars['required'] = $child->vars['required'] && $part->vars['required'];
                $part->children[$name] = $child;
            }
            if ([] !== array_filter($values[$locale] ?? [], static fn (mixed $shown): bool => null !== $shown && '' !== $shown && [] !== $shown)) {
                $nonempty[] = $locale;
            }
            $view->children[$locale] = $part;
        }

        $page = (string) $options['locale'];
        $view->vars = array_replace($view->vars, [
            'type' => 'translation',
            'locale' => $page,
            'single_locale' => $options['single_locale'],
            'default_locale' => $default,
            'available_locales' => $options['available_locales'],
            'required_locales' => $options['required_locales'],
            'nonempty_locales' => $nonempty,
            // The page's language where something is written in it, else the default one.
            'compatible_locale' => $page !== $default && \in_array($page, $nonempty, true) ? $page : $default,
            'translations_empty' => [] === array_filter($view->children, static fn (self $part): bool => [] !== $part->children),
        ]);
    }

    /**
     * A rich kind's vars: its widget, its script's entry, its settings, its
     * routes' addresses on this host.
     *
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    private static function behave(array $vars, \Omnibridge\Form\FieldBehaviour $behaviour, FieldDescriptor $field, mixed $value, string $id): array
    {
        $options = $field->getFormTypeOptions();
        $declared = $behaviour->endpoints($options);
        if (isset($declared['search']) && $behaviour instanceof \Base\Field\Behaviour\Select) {
            $declared['search'] = self::searched($declared['search'], $options);
        }
        $endpoints = array_map(self::address(...), $declared);
        $options['search_url'] = $endpoints['search'] ?? null;
        if ($behaviour instanceof \Base\Field\Behaviour\Select && null !== $field->isRequired()) {
            // The field's word over the select's default, as the admin hands it to SelectType (select2's allowClear).
            $options['required'] = $field->isRequired();
        }
        if ($behaviour instanceof \Base\Field\Behaviour\Select) {
            $chosen = array_map('strval', array_filter(\is_array($value) ? $value : [$value], 'is_scalar'));
            if (isset($vars['choices'])) {
                $options['entries'] = self::entries($behaviour, $vars['choices'], $options, $chosen);
            } elseif (null !== $options['search_url'] && [] !== $chosen) {
                // Searched remotely: select2 starts with the entries chosen, as SelectType gives them.
                $options['entries'] = self::entries($behaviour, array_map(static fn (string $id): array => ['value' => self::record($options, $id) ?? $id, 'id' => $id, 'label' => ''], $chosen), $options, $chosen);
            }
        }
        $extra = $behaviour->vars($options, $value, $id);
        $vars['attr'] = array_replace($vars['attr'], (array) ($extra['attr'] ?? []));
        unset($extra['attr']);
        $assets = $behaviour->assets($options);

        return array_replace($vars, $extra, ['widget' => $assets->controller, 'webpack_entry' => $assets->entry, 'endpoints' => $endpoints]);
    }

    /**
     * select2's entries as SelectType gives them: each choice resolved by omnibase's Autocomplete (an
     * enumeration's word and icon, a record's name) when the host gives omnibase's translator, its label in
     * the page's words otherwise; the chosen ones selected.
     *
     * @param list<array<string, mixed>> $choices
     * @param array<string, mixed>       $options
     * @param list<string>               $chosen
     *
     * @return list<array<string, mixed>>
     */
    private static function entries(\Base\Field\Behaviour\Select $behaviour, array $choices, array $options, array $chosen): array
    {
        try {
            $translator = Runtime::find(\Base\Service\TranslatorInterface::class);
        } catch (\Throwable) {
            $translator = null; // a host that cannot build omnibase's translator: the labels in the page's words
        }
        $translations = Runtime::find(\Omnibridge\Contract\Translations::class);
        $media = Runtime::find(\Base\Service\MediaServiceInterface::class); // a face for a field that asks for one (`avatar`)
        $autocomplete = $translator instanceof \Base\Service\TranslatorInterface ? new \Base\Service\Model\Autocomplete($translator, $media instanceof \Base\Service\MediaServiceInterface ? $media : null) : null;
        $format = null === $options['capitalize'] ? FORMAT_IDENTITY : ($options['capitalize'] ? FORMAT_TITLECASE : FORMAT_SENTENCECASE);
        $class = \is_string($options['class'] ?? null) ? $options['class'] : null;
        $entries = [];
        foreach (self::flat($choices) as $choice) {
            $entry = $autocomplete?->resolve($choice['value'], $class, ['html' => $options['use_html'], 'format' => $format, 'avatar' => $options['avatar']])
                ?? ['id' => \is_object($choice['value']) ? ($choice['id'] ?? null) : $choice['value'], 'icon' => null, 'color' => null, 'search' => null, 'text' => null, 'html' => null, 'data' => []];
            $label = $choice['label'];
            $entry['text'] ??= $label;
            if (null === $class && '' !== $label && $label !== $choice['value']) {
                $entry['text'] = castcase($translations instanceof \Omnibridge\Contract\Translations ? $translations->trans($label) : $label, $format);
            }
            $entry['depth'] = $choice['depth'] ?? 0;
            $entry['selected'] = (int) \in_array((string) (\is_object($choice['value']) ? ($entry['id'] ?? '') : $choice['value']), $chosen, true);
            $entries[] = $behaviour->entry($entry, $options);
        }

        return $entries;
    }

    /**
     * A select's remote search with its `data` (RemoteSearch::data(): what it searches, its token), as
     * SelectType hands it; as it is where the host gives no RemoteSearch (its address is then null).
     *
     * @param array<string, mixed> $options
     */
    private static function searched(Endpoint $endpoint, array $options): Endpoint
    {
        $search = Runtime::find(RemoteSearch::class);
        if (!$search instanceof RemoteSearch || \array_key_exists('data', $endpoint->parameters)) {
            return $endpoint;
        }
        try {
            $data = $search->data(
                \is_string($options['class'] ?? null) ? $options['class'] : null,
                (array) ($options['autocomplete_fields'] ?? []),
                RemoteSearch::filters($options),
                $options['capitalize'] ?? null,
                (bool) ($options['use_html'] ?? false),
                (bool) ($options['avatar'] ?? false),
            );
        } catch (\Throwable) {
            return $endpoint; // no token without a session, no obfuscator's cache: no search
        }

        return new Endpoint($endpoint->route, $endpoint->parameters + ['data' => $data], $endpoint->method);
    }

    /**
     * A record chosen in a select searched remotely, found by its id; null where the class is no
     * record's (an icon, an enumeration's value) or the record is gone.
     *
     * @param array<string, mixed> $options
     */
    private static function record(array $options, string $id): ?object
    {
        $class = $options['class'] ?? null;
        $unitOfWork = Runtime::find(\Omnibridge\Persistence\UnitOfWork::class);
        if (!\is_string($class) || !$unitOfWork instanceof \Omnibridge\Persistence\UnitOfWork) {
            return null;
        }
        try {
            if (!\Base\Database\UnitOfWorks::shapes($unitOfWork)->has($class)) {
                return null;
            }
            $repository = \Base\Database\UnitOfWorks::repositories($unitOfWork)->of($class);

            return $repository->find(ctype_digit($id) ? (int) $id : $id);
        } catch (\Throwable) {
            return null;
        }
    }

    /** The address of a route a widget calls, on this host; null where it has no such route. */
    private static function address(Endpoint $endpoint): ?string
    {
        $routes = Runtime::find(Routes::class);
        $urls = Runtime::find(Urls::class);
        if (!$routes instanceof Routes || !$urls instanceof Urls || null === $routes->pattern($endpoint->route)) {
            return null;
        }
        try {
            return $urls->path($endpoint->route, $endpoint->parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The id of the field a slug is made from: its path in the form, the
     * default language's part of a translations field.
     *
     * @param list<string>|null $path
     */
    private static function target(self $root, ?array $path): ?string
    {
        if (null === $path) {
            return null;
        }
        $id = $root->vars['id'];
        foreach ($path as $part) {
            $id .= '_'.$part;
            if ('translations' === $part) {
                $locales = Runtime::find(Locales::class);
                $id .= '_'.($locales instanceof Locales ? $locales->getDefaultLocale() : \Locale::getDefault());
            }
        }

        return $id;
    }

    /** @param list<array<string, mixed>> $choices @return list<array{value: string, label: string}> */
    private static function flat(array $choices): array
    {
        $flat = [];
        foreach ($choices as $choice) {
            isset($choice['choices']) ? array_push($flat, ...self::flat($choice['choices'])) : $flat[] = $choice;
        }

        return $flat;
    }

    private static function type(string $kind, FieldDescriptor $field): string
    {
        if (Kind::isChoice($kind) || null !== $field->getOption('choices')) {
            return 'choice';
        }

        return match ($kind) {
            Kind::BOOLEAN, Kind::CHECKBOX => 'checkbox',
            Kind::TEXTAREA, Kind::EDITOR, Kind::WYSIWYG, Kind::CODE => 'textarea',
            Kind::HTML => 'html',
            // Its two inputs are its children: a renderer draws a row for each (was a text input).
            Kind::REPEATED => 'repeated',
            Kind::SUBMIT => 'submit',
            Kind::BUTTON => 'button',
            default => Kind::inputType($kind) ?? 'text',
        };
    }

    /**
     * The choices as a list of ['value' => string, 'label' => string] (an
     * order a JavaScript object would not keep), a group as ['label' =>, 'choices' => [...]].
     *
     * @return list<array<string, mixed>>
     */
    private static function choices(iterable $choices, FieldDescriptor $field): array
    {
        $binder = new FormBinder();
        $list = [];
        foreach ($choices as $label => $value) {
            if (\is_array($value)) {
                // A group that is a choice itself (an enumeration's `_self`, as SelectType reads it): the group's value, then its own, flat.
                if (\array_key_exists('_self', $value)) {
                    $self = $value['_self'];
                    unset($value['_self']);
                    array_push($list, ...self::choices([$self], $field), ...array_map(static fn (array $choice): array => $choice + ['depth' => 1], self::choices($value, $field)));
                    continue;
                }
                $list[] = ['label' => (string) $label, 'choices' => self::choices($value, $field)];
                continue;
            }
            $shown = $binder->transform($value, (clone $field)->setFormTypeOption('multiple', false));
            // A list of records, as an entity's choices: each says its own name.
            $list[] = ['value' => \is_scalar($shown) ? (string) $shown : '', 'label' => \is_object($value) && \is_int($label) ? (string) $value : (string) $label];
        }

        return $list;
    }

    private static function isMultipart(FormDefinition $form): bool
    {
        foreach ($form->getFields() as $field) {
            if (Kind::isFile($field->getKind())) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $vars */
    public function add(string $name, array $vars = []): self
    {
        $this->children[$name] = new self($name, $vars, $this);

        return $this;
    }

    /** What omnibase's FormProxy hands: a form whose view is made on demand. */
    public function createView(): self
    {
        return $this;
    }

    /** Whether a choice value is selected by the view's value. */
    public function isSelected(string $value): bool
    {
        $current = $this->vars['value'];

        return \is_array($current) ? \in_array($value, array_map('strval', $current), true) : (string) $current === $value;
    }

    /**
     * The view as plain data (a React page's props): vars and children, recursively.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $vars = $this->vars;
        $vars['children'] = [];
        foreach ($this->children as $name => $child) {
            $vars['children'][] = $child->toArray();
        }

        return $vars;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->children[$offset]);
    }

    public function offsetGet(mixed $offset): FormView
    {
        return $this->children[$offset] ?? throw new \OutOfBoundsException(sprintf('The form view "%s" has no child "%s".', $this->vars['full_name'], $offset));
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('A form view is read only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('A form view is read only.');
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->children);
    }

    public function count(): int
    {
        return \count($this->children);
    }
}
