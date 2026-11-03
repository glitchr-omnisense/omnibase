<?php

namespace Base\Form\Definition;

use Base\Field\Behaviour\Association;
use Base\Field\Behaviour\AssociationFile;
use Base\Field\Behaviour\Attribute;
use Base\Field\Behaviour\Collection;
use Base\Field\Behaviour\Behaviours;
use Base\Field\Behaviour\Guess;
use Base\Field\Behaviour\Translation;
use Base\Field\Behaviour\Editor;
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
            if (Kind::ASSOCIATION_FILE === $field->getKind() && !$field->hasExplicitFormType()) {
                $root->children[$name] = self::associationFile($root, $name, $field, $data, $errors[$name] ?? [], $form->getDataClass());
                continue;
            }
            if (Kind::ASSOCIATION === $field->getKind() && !$field->hasExplicitFormType() && null === $field->getOption('choices')) {
                // Embedded (AssociationType); one given its choices is a list of records to pick, drawn as a choice.
                $root->children[$name] = self::association($root, $name, $field, $data, $errors[$name] ?? [], $form->getDataClass());
                continue;
            }
            if (Kind::ATTRIBUTE === $field->getKind() && !$field->hasExplicitFormType()) {
                $root->children[$name] = self::attribute($root, $name, $field, $data, $errors[$name] ?? [], $form->getDataClass());
                continue;
            }
            if (Behaviours::ofField($field) instanceof Upload) {
                $field = self::uploaded($field, $name, $data, $values[$name] ?? null);
            }
            if (Behaviours::ofField($field) instanceof Editor || (Kind::TRANSLATION === $field->getKind() && !$field->hasExplicitFormType())) {
                // An editor's collaboration room is the form's record's (EditorType: the root form's data), its field's name.
                $field = (clone $field)->setFormTypeOptions($field->getFormTypeOptions() + ['editor_record' => \is_object($data) ? $data : null, 'editor_field' => $name]);
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

    /**
     * Records made from uploads (AssociationFile), as AssociationFileType builds them: a compound
     * (widget `associationfile`) holding one upload field named by the first part of `entity_file`, which
     * shows the related records' files; its vars are the type's - multiple, allow_delete, href, entityId
     * (each stored file's name by its record's id, which the block gives the upload's data-entry-id).
     *
     * @param list<string> $errors
     */
    private static function associationFile(self $root, string $name, FieldDescriptor $field, mixed $data, array $errors, ?string $dataClass): self
    {
        $options = AssociationFile::options($field->getFormTypeOptions());
        $property = (string) ($field->getOption('property_path') ?? $field->getProperty() ?? $name);
        $records = \is_object($data) && false !== $field->getOption('mapped') && Properties::isReadable($data, $property) ? Properties::get($data, $property) : null;
        $label = $field->getLabel() ?? $field->getOption('label');
        $view = new self($name, [
            'kind' => Kind::ASSOCIATION_FILE,
            'type' => 'association_file',
            'widget' => 'associationfile',
            'value' => null,
            'label' => false === $label ? false : (null !== $label ? (string) $label : null),
            'help' => null !== ($help = $field->getHelp() ?? $field->getOption('help')) ? (string) $help : null,
            'errors' => array_values($errors),
            'attr' => array_replace($field->getHtmlAttributes(), (array) $field->getOption('attr', [])),
            'row_attr' => (array) $field->getOption('row_attr', []),
            'required' => (bool) ($field->isRequired() ?? $field->getOption('required') ?? true),
            'disabled' => (bool) ($field->isDisabled() ?? $field->getOption('disabled') ?? false),
            'multiple' => (bool) $options['multiple'],
            'allow_delete' => $options['allow_delete'],
            'href' => $options['href'],
            'entityId' => AssociationFile::entries($records, $options),
            'explicit' => false,
            'translation_domain' => $field->getOption('translation_domain') ?? $root->vars['translation_domain'],
        ], $root);

        $child = AssociationFile::child($options);
        $required = AssociationFile::required($dataClass ?? (\is_object($data) ? $data::class : null), $property);
        $upload = FormDefinition::field($child, AssociationFile::kind($options))
            ->setFormTypeOptions(AssociationFile::childOptions($options, $required) + [
                // What the upload's block reads (Upload::vars()): the related record, or the collection, and its files.
                'upload_record' => \is_object($records) ? $records : null, 'upload_property' => $child, 'upload_data' => AssociationFile::files($records, $options),
            ])
            ->setRequired($required)
            ->setDisabled($view->vars['disabled']);
        $view->children[$child] = self::field($view, $child, $upload, null, []);

        return $view;
    }

    /**
     * The common vars of a field built here as a compound (an embedded record, its attributes).
     *
     * @param list<string> $errors
     *
     * @return array<string, mixed>
     */
    private static function compound(self $root, FieldDescriptor $field, string $kind, array $errors): array
    {
        $label = $field->getLabel() ?? $field->getOption('label');

        return [
            'kind' => $kind, 'type' => $kind, 'widget' => $kind, 'value' => null,
            'label' => false === $label ? false : (null !== $label ? (string) $label : null),
            'help' => null !== ($help = $field->getHelp() ?? $field->getOption('help')) ? (string) $help : null,
            'errors' => array_values($errors),
            'attr' => array_replace($field->getHtmlAttributes(), (array) $field->getOption('attr', [])),
            'row_attr' => (array) $field->getOption('row_attr', []),
            'disabled' => (bool) ($field->isDisabled() ?? $field->getOption('disabled') ?? false),
            'explicit' => false,
            'translation_domain' => $field->getOption('translation_domain') ?? $root->vars['translation_domain'],
        ];
    }

    /**
     * A related record's form embedded (Association), as AssociationType builds it: one record, its fields
     * (and its `_index`); several, the collection `_collection` (widget collection2) whose entries, by their
     * index in the association, are each one record's form - the window a capped collection shows, its
     * total -, and, where entries are added, the prototype of a new one (vars `prototype`, named
     * `__prototype__`). Its vars are the type's: multiple, group, row_group, allow_add, allow_delete,
     * keep_indexes, length, href, html.
     *
     * @param list<string> $errors
     */
    private static function association(self $root, string $name, FieldDescriptor $field, mixed $data, array $errors, ?string $dataClass): self
    {
        $property = (string) ($field->getOption('property_path') ?? $field->getProperty() ?? $name);
        $owner = $dataClass ?? (\is_object($data) ? $data::class : null);
        $given = $field->getFormTypeOptions();
        $given['class'] = Association::targetClass($given, $owner, $property);
        $given['multiple'] = Association::isMultiple($given, $owner, $property);
        $options = Association::options($given);
        $value = \is_object($data) && false !== $field->getOption('mapped') && Properties::isReadable($data, $property) ? Properties::get($data, $property) : null;

        $view = new self($name, self::compound($root, $field, Kind::ASSOCIATION, $errors) + [
            'required' => $options['multiple'] ? false : (bool) ($field->isRequired() ?? $options['required']),
            'multiple' => $options['multiple'], 'group' => $options['group'], 'row_group' => $options['row_group'],
            'allow_add' => $options['allow_add'], 'allow_delete' => $options['allow_delete'], 'keep_indexes' => $options['keep_indexes'],
            'length' => $options['length'], 'href' => $options['href'], 'html' => $options['html'],
        ], $root);
        if (null === $options['class']) {
            throw new \RuntimeException('Unable to get "class" or compute "data_class" from form "'.$name.'": define the "class" option of its association field.');
        }
        $fields = Association::fields($options['class'], $options, $name);
        if (!$options['multiple']) {
            self::entry($view, $fields, $options['class'], \is_object($value) ? $value : null, $options, $view);

            return $view;
        }

        $collection = Collection::options(Association::collection($options));
        $records = Association::records($value);
        $shown = Collection::window($records, $collection);
        $list = new self(Association::COLLECTION, [
            'kind' => Kind::COLLECTION, 'type' => Kind::COLLECTION, 'widget' => 'collection2', 'label' => false, 'required' => true,
            'value' => $shown, 'entry_total' => \count($records),
        ] + self::listed($collection), $view);
        foreach ($shown as $index => $record) {
            $entry = new self((string) $index, ['kind' => 'form', 'type' => 'form', 'widget' => 'association_entry', 'label' => false, 'required' => true, 'value' => $record, 'group' => $options['group'], 'row_group' => $options['row_group']], $list);
            self::entry($entry, $fields, $options['class'], $record, $options, $entry);
            $list->children[(string) $index] = $entry;
        }
        if ($collection['allow_add'] && $collection['prototype']) {
            $prototype = new self((string) $collection['prototype_name'], ['kind' => 'form', 'type' => 'form', 'widget' => 'association_entry', 'label' => false, 'required' => true, 'value' => null, 'group' => $options['group'], 'row_group' => $options['row_group'], 'attr' => ['placeholder' => self::trans('@fields.array.value')]], $list);
            self::entry($prototype, $fields, $options['class'], null, $options, $prototype);
            $list->vars['prototype'] = $prototype;
        }
        $view->children[Association::COLLECTION] = $list;
        if (!$view->vars['required']) {
            self::unrequire($view); // Symfony's forms: nothing inside a field not required is
        }

        return $view;
    }

    /** A field not required: nothing inside it is, its prototypes' fields included. */
    private static function unrequire(self $view): void
    {
        foreach ($view->children as $child) {
            $child->vars['required'] = false;
            self::unrequire($child);
        }
        if (($view->vars['prototype'] ?? null) instanceof self) {
            $view->vars['prototype']->vars['required'] = false;
            self::unrequire($view->vars['prototype']);
        }
    }

    /**
     * One record's form inside an embedded association: its fields with the values it holds, its `_index`.
     *
     * @param array<string, FieldDescriptor> $fields
     * @param array<string, mixed>           $options
     */
    private static function entry(self $parent, array $fields, string $class, ?object $record, array $options, self $into): void
    {
        $definition = FormDefinition::new('entry')->setCsrf(false)->setDataClass($class);
        foreach ($fields as $descriptor) {
            $definition->add($descriptor);
        }
        $values = null !== $record ? (new FormBinder(self::unitOfWork()))->read($definition, $record) : [];
        foreach ($fields as $name => $descriptor) {
            $value = $values[$name] ?? null;
            if (null !== $record && \in_array($descriptor->getKind(), [Kind::COLLECTION, Kind::ARRAY], true) && Properties::isReadable($record, $name)) {
                $value = Properties::get($record, $name); // its entries as they are (records listed by a collection)
            }
            $into->children[$name] = self::field($parent, $name, Guess::from($descriptor, $class), $value, []);
        }
        if ($options['keep_indexes']) {
            $into->children[Association::INDEX] = new self(Association::INDEX, ['kind' => Kind::HIDDEN, 'type' => 'hidden', 'value' => null, 'required' => false], $parent);
        }
    }

    /**
     * A record's attributes (Attribute), as AttributeType builds them: its select of adapters (`choice`,
     * searched remotely), then for each attribute held its part, named by its adapter's code and its id,
     * holding its value's field (`value`) - a translated one's marked (`translated`: its value is a
     * TranslationType there). Its vars are the type's: multiple, allow_add, allow_delete, is_inherited.
     *
     * @param list<string> $errors
     */
    private static function attribute(self $root, string $name, FieldDescriptor $field, mixed $data, array $errors, ?string $dataClass): self
    {
        $property = (string) ($field->getOption('property_path') ?? $field->getProperty() ?? $name);
        $owner = $dataClass ?? (\is_object($data) ? $data::class : null);
        $options = Attribute::options($field->getFormTypeOptions());
        $shape = self::shapeOf($owner);
        $options = array_replace($options, Attribute::classes($options, null !== $shape && $shape->hasAssociation($property) ? $shape->targetClass($property) : null));
        $options['multiple'] ??= null !== $shape && $shape->hasAssociation($property) ? $shape->isToMany($property) : false;
        if (null === $options['sortable'] && null !== $owner) {
            $guessed = Guess::from(FormDefinition::field($property, Kind::CHOICE)->setProperty($property)->setFormTypeOptions(['class' => $options['abstract_class'], 'multivalue' => $options['multivalue']]), $owner);
            $options['sortable'] = (bool) $guessed->getOption('sortable');
        }
        $value = \is_object($data) && false !== $field->getOption('mapped') && Properties::isReadable($data, $property) ? Properties::get($data, $property) : null;
        $held = Attribute::held($value, (bool) $options['multiple']);

        $view = new self($name, self::compound($root, $field, Kind::ATTRIBUTE, $errors) + [
            'required' => (bool) ($field->isRequired() ?? $field->getOption('required') ?? true),
            'multiple' => (bool) $options['multiple'], 'allow_add' => $options['allow_add'], 'allow_delete' => $options['allow_delete'], 'is_inherited' => false,
        ], $root);
        $chosen = array_values(array_filter(array_map(static fn (\Base\Entity\Layout\Attribute\Common\AbstractAttribute $attribute): ?string => null !== ($id = $attribute->getAdapter()?->getId()) ? (string) $id : null, $held)));
        $choice = FormDefinition::field('choice', Kind::CHOICE)->setFormTypeOptions(Attribute::choice($options, \is_object($data) ? $data : null))->setRequired($view->vars['required'])->setLabel(false);
        $view->children['choice'] = self::field($view, 'choice', $choice, $options['multiple'] ? $chosen : ($chosen[0] ?? null), []);
        foreach ($held as $attribute) {
            $key = Attribute::key($attribute);
            $part = new self($key, ['kind' => Kind::ASSOCIATION, 'type' => Kind::ASSOCIATION, 'widget' => Kind::ASSOCIATION, 'label' => false, 'required' => false, 'multiple' => false, 'group' => false, 'row_group' => false, 'value' => $attribute, 'translated' => Attribute::translated($attribute)], $view);
            if (!Attribute::translated($attribute)) {
                $descriptor = Attribute::value($attribute);
                $part->children['value'] = self::field($part, 'value', $descriptor, (new FormBinder(self::unitOfWork()))->transform($attribute->get(), $descriptor), []);
            }
            $view->children[$key] = $part;
        }

        return $view;
    }

    /**
     * A list's entries (Collection), as CollectionType and ArrayType build them: one field of the entry's kind
     * per entry (an associative array's, its key and its value), padded to a fixed length, the prototype of a
     * new one where entries are added (vars `prototype`). Its vars are the types': allow_add, allow_delete,
     * group, row_group, entry_collapsed, length, max_entries, entry_offset, entry_total, prototype_name,
     * pattern, placeholder, associative, html, href.
     */
    private static function collection(self $view, FieldDescriptor $field, mixed $value): void
    {
        $kind = (string) $field->getKind();
        $options = Collection::options($field->getFormTypeOptions(), $kind);
        $entries = Collection::shown($value, $options + ['kind' => $kind]);
        if (\is_int($options['length']) && $options['length'] > 0) {
            $entries = array_pad($entries, $options['length'], null);
        }
        $total = \count($entries);
        $entries = Collection::window($entries, $options);
        $view->vars = array_replace($view->vars, ['type' => $kind, 'widget' => Kind::ARRAY === $kind ? 'array' : 'collection2', 'value' => $entries, 'required' => true, 'entry_total' => $total] + self::listed($options), [
            'pattern' => $options['pattern'] ?? null, 'placeholder' => $options['placeholder'] ?? [], 'associative' => $options['associative'] ?? null,
        ]);
        $type = $options['entry_type'];
        $entryKind = \is_string($type) && class_exists($type) ? \Base\Field\Behaviour\RecordFields::kindOf($type) : (string) $type;
        $entryOptions = array_diff_key((array) $options['entry_options'], ['block_name' => true, 'label' => true]);
        $make = static function (string $name, mixed $shown, array $attr = []) use ($view, $entryKind, $entryOptions, $options): self {
            $descriptor = FormDefinition::field($name, $entryKind)->setFormTypeOptions(array_replace_recursive($entryOptions, [] !== $attr ? ['attr' => $attr] : []))->setLabel(false);
            if (null !== $options['entry_required']) {
                $descriptor->setRequired((bool) $options['entry_required']);
            }
            if ($options['associative'] ?? false) {
                $entry = new self($name, ['kind' => 'form', 'type' => 'form', 'label' => false, 'required' => true], $view);
                $entry->children['key'] = self::field($entry, 'key', FormDefinition::field('key', Kind::TEXT)->setLabel(false)->setFormTypeOptions(['attr' => ['placeholder' => self::trans('@fields.array.key')]]), \is_array($shown) ? ($shown['key'] ?? null) : null, []);
                $entry->children['value'] = self::field($entry, 'value', $descriptor->setProperty('value'), \is_array($shown) ? ($shown['value'] ?? null) : null, []);

                return $entry;
            }

            return self::field($view, $name, $descriptor, \is_object($shown) ? (method_exists($shown, '__toString') ? (string) $shown : (method_exists($shown, 'getId') ? $shown->getId() : null)) : $shown, []);
        };
        foreach ($entries as $index => $shown) {
            $view->children[(string) $index] = $make((string) $index, $shown);
        }
        if ($options['allow_add'] && $options['prototype']) {
            $view->vars['prototype'] = $make((string) $options['prototype_name'], null, ['placeholder' => self::trans('@fields.array.value')]);
        }
    }

    /**
     * A list's vars, as CollectionType gives them to its block.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function listed(array $options): array
    {
        return [
            'allow_add' => $options['allow_add'], 'allow_delete' => $options['allow_delete'], 'group' => $options['group'],
            'row_group' => $options['row_group'], 'entry_collapsed' => $options['entry_collapsed'], 'length' => $options['length'],
            'max_entries' => $options['max_entries'], 'entry_offset' => max(0, (int) $options['entry_offset']),
            'prototype_name' => $options['allow_add'] && $options['prototype'] ? $options['prototype_name'] : null,
            'html' => $options['html'], 'href' => $options['href'], 'empty' => Collection::empty(),
        ];
    }

    private static function trans(string $key): string
    {
        $translations = Runtime::find(\Omnibridge\Contract\Translations::class);

        return $translations instanceof \Omnibridge\Contract\Translations ? $translations->trans($key) : $key;
    }

    private static function unitOfWork(): ?\Omnibridge\Persistence\UnitOfWork
    {
        $unitOfWork = Runtime::find(\Omnibridge\Persistence\UnitOfWork::class);

        return $unitOfWork instanceof \Omnibridge\Persistence\UnitOfWork ? $unitOfWork : null;
    }

    private static function shapeOf(?string $class): ?\Base\Database\Mapping\EntityShape
    {
        $unitOfWork = self::unitOfWork();
        if (null === $class || null === $unitOfWork) {
            return null;
        }
        try {
            $shapes = \Base\Database\UnitOfWorks::shapes($unitOfWork);

            return $shapes->has($class) ? $shapes->shape($class) : null;
        } catch (\Throwable) {
            return null;
        }
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
        if ((Kind::COLLECTION === $kind || Kind::ARRAY === $kind) && !$field->hasExplicitFormType()) {
            self::collection($view, $field, $value);
        }
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
                if (Behaviours::ofField($descriptor) instanceof Editor) {
                    // The form's record, as EditorType reads its root form's data; no language (TranslationType gives its own field one).
                    $descriptor = (clone $descriptor)->setFormTypeOptions($descriptor->getFormTypeOptions() + ['editor_record' => $field->getOption('editor_record'), 'editor_field' => $name]);
                }
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
