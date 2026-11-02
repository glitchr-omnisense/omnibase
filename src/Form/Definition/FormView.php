<?php

namespace Base\Form\Definition;

use Base\Field\FieldDescriptor;
use Base\Field\Kind;

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
     */
    public static function of(FormDefinition $form, array $values = [], array $errors = [], ?string $token = null, bool $submitted = false): self
    {
        $root = new self($form->getName(), [
            'kind' => 'form', 'type' => 'form', 'method' => $form->getMethod(), 'action' => $form->getTarget() ?? '',
            'attr' => $form->getAttr(), 'errors' => array_values($errors[''] ?? []), 'submitted' => $submitted,
            'valid' => [] === array_filter($errors), 'translation_domain' => $form->getTranslationDomain(),
            'groups' => $form->getGroups(), 'multipart' => self::isMultipart($form),
        ]);

        foreach ($form->getFields() as $name => $field) {
            $root->children[$name] = self::field($root, $name, $field, $values[$name] ?? null, $errors[$name] ?? []);
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
                'kind' => Kind::HIDDEN, 'type' => 'hidden', 'value' => $token,
            ], $root);
        }

        return $root;
    }

    /** @param list<string> $errors */
    private static function field(self $root, string $name, FieldDescriptor $field, mixed $value, array $errors): self
    {
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
            'translation_domain' => $field->getOption('translation_domain') ?? $root->vars['translation_domain'],
        ];
        if (Kind::BOOLEAN === $kind || Kind::CHECKBOX === $kind) {
            $vars['checked'] = (bool) $value;
            $vars['value'] = (string) ($field->getOption('value') ?? '1');
        }
        if (null !== $field->getOption('choices')) {
            $vars['choices'] = self::choices(FormBinder::choices($field) ?? [], $field);
            $vars['placeholder'] = $field->getOption('placeholder');
        }

        $view = new self($name, $vars, $root);
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

    private static function type(string $kind, FieldDescriptor $field): string
    {
        if (Kind::isChoice($kind) || null !== $field->getOption('choices')) {
            return 'choice';
        }

        return match ($kind) {
            Kind::BOOLEAN, Kind::CHECKBOX => 'checkbox',
            Kind::TEXTAREA, Kind::EDITOR, Kind::WYSIWYG, Kind::CODE => 'textarea',
            Kind::HTML => 'html',
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
