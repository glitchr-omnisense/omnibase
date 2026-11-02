<?php

namespace Base\Form\Definition;

use Base\Field\FieldDescriptor;
use Base\Field\FieldInterface;
use Base\Field\Kind;

/**
 * A form, neutral: its fields (FieldDescriptors, each naming a kind), their
 * groups, its actions (the buttons that submit it), the intent its token is
 * made for, how it is sent. No host's form library: Symfony builds its form
 * from a definition (omnibridge/symfony), a Twig, Blade or React renderer
 * prints its FormView, FormProcessor binds, validates and keeps what is sent.
 *
 *     $form = FormDefinition::new('comment')
 *         ->add(TextareaField::new('content', 'comment.content')->setRequired(true))
 *         ->addAction('submit', 'comment.submit');
 *
 * The admin builds one from a CRUD controller's fields (fromFields()).
 */
final class FormDefinition
{
    public const METHOD_POST = 'POST';
    public const METHOD_GET = 'GET';

    /** The name a submitted token is sent under. */
    public const TOKEN_FIELD = '_csrf_token';

    /** @var array<string, FieldDescriptor> by name */
    private array $fields = [];

    /** @var array<string, array{label: string|null, fields: list<string>}> */
    private array $groups = [];

    /** @var array<string, array{label: string|null, kind: string, attr: array<string, mixed>, options: array<string, mixed>}> */
    private array $actions = [];

    private string $method = self::METHOD_POST;
    private ?string $target = null;
    private ?string $intent = null;
    private bool $csrf = true;
    private ?string $dataClass = null;
    private ?string $translationDomain = null;

    /** @var array<string, mixed> */
    private array $attr = [];

    /** @var array<string, mixed> what a host-only feature needs (Symfony's form options...) */
    private array $hostOptions = [];

    /** @var array<string, mixed>|false Base\Service\FormGuard's options, false for none */
    private array|false $guard = false;

    /** @var list<callable(array<string, mixed>): array<string, mixed>> */
    private array $rewrites = [];

    /** @var list<string>|null */
    private ?array $validationGroups = null;

    /** @var class-string|null */
    private ?string $validationEntity = null;

    /** @var list<string> */
    private array $entityErrorFields = [];

    public function __construct(private readonly string $name)
    {
        if ('' === $name || !preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_\-:]*$/', $name)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a form name: letters, digits, "_", "-" and ":", not starting with "-" or ":".', $name));
        }
    }

    public static function new(string $name): self
    {
        return new self($name);
    }

    /**
     * A bare field of a kind (Base\Field\Kind), for a form that is no CRUD
     * page's: none of what a descriptor class adds for the back office.
     */
    public static function field(string $name, string $kind, ?string $label = null): FieldDescriptor
    {
        return (new FieldDescriptor())->setProperty($name)->setKind($kind)->setLabel($label);
    }

    /**
     * The form of the fields a page shows (a CRUD controller's
     * configureFields()): those displayed on that page, in order.
     *
     * @param iterable<FieldInterface|FieldDescriptor> $fields
     */
    public static function fromFields(string $name, iterable $fields, string $page = FieldDescriptor::PAGE_NEW): self
    {
        $form = new self($name);
        foreach ($fields as $field) {
            $descriptor = $field instanceof FieldInterface ? $field->getAsDto() : $field;
            if ($descriptor->isDisplayedOn($page)) {
                $form->add($descriptor);
            }
        }

        return $form;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Adds a field, under its property's name (with its suffix), optionally
     * in a group. A field of the same name is replaced.
     */
    public function add(FieldInterface|FieldDescriptor $field, ?string $group = null): self
    {
        $descriptor = $field instanceof FieldInterface ? $field->getAsDto() : $field;
        $name = $descriptor->getPropertyWithSuffix();
        if (null === $name || '' === $name) {
            throw new \InvalidArgumentException(sprintf('A field of the form "%s" has no property name.', $this->name));
        }
        if (null === $descriptor->getKind()) {
            throw new \InvalidArgumentException(sprintf('The field "%s" of the form "%s" names no kind (Base\Field\Kind).', $name, $this->name));
        }

        $this->fields[$name] = $descriptor;
        if (null !== $group) {
            $this->groups[$group] ??= ['label' => null, 'fields' => []];
            if (!\in_array($name, $this->groups[$group]['fields'], true)) {
                $this->groups[$group]['fields'][] = $name;
            }
        }

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->fields[$name]);
        foreach ($this->groups as &$group) {
            $group['fields'] = array_values(array_diff($group['fields'], [$name]));
        }

        return $this;
    }

    /**
     * A copy of the form with only these fields, its other settings kept: a host's type that adds
     * them to its parent's asks its own part.
     *
     * @param list<string> $names
     */
    public function only(array $names): self
    {
        $form = clone $this;
        $form->fields = array_intersect_key($this->fields, array_flip($names));

        return $form;
    }

    public function has(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    public function get(string $name): FieldDescriptor
    {
        return $this->fields[$name] ?? throw new \OutOfBoundsException(sprintf('The form "%s" has no field "%s".', $this->name, $name));
    }

    /** @return array<string, FieldDescriptor> by name, in order */
    public function getFields(): array
    {
        return $this->fields;
    }

    /** A group of fields (a fieldset, a tab), its label a translatable key. */
    public function addGroup(string $name, ?string $label = null): self
    {
        $this->groups[$name] = ['label' => $label, 'fields' => $this->groups[$name]['fields'] ?? []];

        return $this;
    }

    /** @return array<string, array{label: string|null, fields: list<string>}> */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * A button that submits the form: its name is the submitted intent
     * (FormSubmission::getClicked()), its label a translatable key.
     *
     * The options are plain data a renderer reads: `confirmation` (the button
     * asks in a modal first) and `type` (what its HTML element says: "submit"
     * on a button that is an action of Kind::BUTTON).
     *
     * @param array<string, mixed> $attr
     * @param array<string, mixed> $options
     */
    public function addAction(string $name, ?string $label = null, string $kind = Kind::SUBMIT, array $attr = [], array $options = []): self
    {
        $this->actions[$name] = ['label' => $label, 'kind' => $kind, 'attr' => $attr, 'options' => $options];

        return $this;
    }

    /** @return array<string, array{label: string|null, kind: string, attr: array<string, mixed>, options: array<string, mixed>}> */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    /** Where the form is sent (its `action` attribute); null for the page itself. */
    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $url): self
    {
        $this->target = $url;

        return $this;
    }

    /** The intent its token is made for (Omnibridge\Contract\Tokens): the form's name unless set. */
    public function getIntent(): string
    {
        return $this->intent ?? $this->name;
    }

    /** Whether an intent was set: without one, a host may name the token as it does (Symfony: the form's own name). */
    public function hasIntent(): bool
    {
        return null !== $this->intent;
    }

    public function setIntent(?string $intent): self
    {
        $this->intent = $intent;

        return $this;
    }

    public function hasCsrf(): bool
    {
        return $this->csrf;
    }

    public function setCsrf(bool $csrf): self
    {
        $this->csrf = $csrf;

        return $this;
    }

    /** The class of the data the form edits (an entity, a DTO); null for an array. */
    public function getDataClass(): ?string
    {
        return $this->dataClass;
    }

    public function setDataClass(?string $dataClass): self
    {
        $this->dataClass = $dataClass;

        return $this;
    }

    /** The catalogue its labels, helps and placeholders are translated from; null for the host's default (Symfony's: `fields`). */
    public function getTranslationDomain(): ?string
    {
        return $this->translationDomain;
    }

    public function setTranslationDomain(?string $domain): self
    {
        $this->translationDomain = $domain;

        return $this;
    }

    /**
     * Base\Service\FormGuard over this form: the trap, the time, the lists, the
     * captcha. true, or Symfony's option `guard`'s array: trap, min_delay,
     * challenge, action, reputation, email, name, captcha_after.
     *
     * @param array<string, mixed>|bool $guard
     */
    public function setGuard(array|bool $guard): self
    {
        $this->guard = true === $guard ? [] : $guard;

        return $this;
    }

    /** @return array<string, mixed>|false */
    public function getGuard(): array|false
    {
        return $this->guard;
    }

    /**
     * What was sent, rewritten before it is read (Symfony's PRE_SUBMIT): a
     * "tick everything" box expanded into the list it stands for.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $rewrite
     */
    public function addRewrite(callable $rewrite): self
    {
        $this->rewrites[] = $rewrite;

        return $this;
    }

    /**
     * @param array<string, mixed> $submitted
     *
     * @return array<string, mixed>
     */
    public function rewrite(array $submitted): array
    {
        foreach ($this->rewrites as $rewrite) {
            $submitted = $rewrite($submitted);
        }

        return $submitted;
    }

    /** @return list<string>|null the groups the form validates, the host's default when null */
    public function getValidationGroups(): ?array
    {
        return $this->validationGroups;
    }

    /** @param list<string>|null $groups */
    public function setValidationGroups(?array $groups): self
    {
        $this->validationGroups = $groups;

        return $this;
    }

    /** @return class-string|null the record the form makes, whose class-level rules (its uniqueness) the data must hold */
    public function getValidationEntity(): ?string
    {
        return $this->validationEntity;
    }

    /**
     * @param class-string|null $class
     * @param list<string>      $errorFields the fields whose refusal is said under them; the others' is the form's own
     */
    public function setValidationEntity(?string $class, array $errorFields = []): self
    {
        $this->validationEntity = $class;
        $this->entityErrorFields = $errorFields;

        return $this;
    }

    /** @return list<string> */
    public function getEntityErrorFields(): array
    {
        return $this->entityErrorFields;
    }

    /** @return array<string, mixed> the <form> element's attributes */
    public function getAttr(): array
    {
        return $this->attr;
    }

    /** @param array<string, mixed> $attr */
    public function setAttr(array $attr): self
    {
        $this->attr = $attr;

        return $this;
    }

    /**
     * Options only one host reads (Symfony's form options: 'validation_groups',
     * 'allow_extra_fields'...): the escape hatch, ignored by the others.
     *
     * @return array<string, mixed>
     */
    public function getHostOptions(string $host = 'symfony'): array
    {
        return $this->hostOptions[$host] ?? [];
    }

    /** @param array<string, mixed> $options */
    public function setHostOptions(array $options, string $host = 'symfony'): self
    {
        $this->hostOptions[$host] = $options;

        return $this;
    }
}
