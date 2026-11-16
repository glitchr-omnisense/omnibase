<?php

namespace Base\Form\Definition;

use Base\Database\Repository\Repositories;
use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Base\Field\Kinds;
use Omnibridge\Validation\AttributeConstraints;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The forms of an application and its bundles as they are written - Symfony form types, App\Form\XType's
 * buildForm() and configureOptions(), naming omnibase's Base\Field\Type\* and Symfony's core types - read as
 * omnibase's neutral FormDefinition, which this package draws (FormRenderer) and omnibase's FormProcessor binds and
 * validates. One way to write a form: the same class serves the Symfony site and its Laravel host.
 *
 * Reading runs the type as Symfony's factory would: its parents' options, then its own (configureOptions), then
 * buildForm() on a builder that records each field - its name, its type, its options - without building it (no
 * Symfony type is instantiated but the application's). A field's kind is its type's (Base\Field\Kinds when
 * omnibridge/symfony is installed, else the type's name: Base\Field\Type\XType is omnibase's Base\Field\XField,
 * Symfony's core XType the kind of that name); its options are kept as the descriptor's (FormView and FormBinder
 * read Symfony's option names: attr, row_attr, choices, class, multiple, placeholder...). What cannot be read is
 * said (read()'s second part), never guessed: a form event, a data transformer, a field of an unknown or compound
 * type, a constraint no reader knows.
 *
 * Asked through FormDefinition::of(); each host registers how its form types are made (register(): Laravel's
 * container, Symfony's form registry - a type's constructor takes its services).
 *
 * @internal an application never names it: FormDefinition::of()
 */
final class TypeReader
{
    public const TYPE = 'base.form.type';
    public const OPTIONS = 'base.form.options';
    private const CORE = 'Symfony\Component\Form\Extension\Core\Type\\';
    private const FIELDS = 'Base\Field\Type\\';

    /** Symfony's types whose kind is not their own name. */
    private const KINDS = [
        self::CORE.'ChoiceType' => Kind::CHOICE,
        self::CORE.'EnumType' => Kind::CHOICE,
        self::CORE.'BirthdayType' => Kind::DATE,
        self::CORE.'ResetType' => Kind::BUTTON,
        self::FIELDS.'SelectType' => Kind::CHOICE,
        self::FIELDS.'TranslatableType' => Kind::TRANSLATION,
    ];

    /** What Symfony's FormType and omnibase's form extensions define on every form: a type may read or set them. */
    private const FORM_OPTIONS = [
        'data_class' => null, 'empty_data' => null, 'data' => null, 'translation_domain' => null, 'label' => null,
        'label_translation_parameters' => [], 'attr' => [], 'row_attr' => [], 'help' => null, 'required' => true,
        'disabled' => false, 'mapped' => true, 'compound' => true, 'method' => 'POST', 'action' => '',
        'csrf_protection' => true, 'csrf_token_id' => null, 'csrf_field_name' => '_token', 'validation_groups' => null,
        'constraints' => [], 'allow_extra_fields' => false, 'error_bubbling' => true, 'by_reference' => true,
        'inherit_data' => false, 'property_path' => null, 'block_prefix' => null, 'priority' => 0,
        // omnibase's (base-bundle's form extensions): the guard, the record validated, the form's theme.
        'guard' => false, 'validation_entity' => null, 'form2' => true,
    ];

    private static ?self $reader = null;

    /**
     * The host's way to make a form type and to reach the repositories (at its boot); without one, a type is made with
     * no argument and a select of records lists none.
     *
     * @param \Closure(class-string): object  $make
     * @param (\Closure(): Repositories)|null $repositories
     */
    public static function register(\Closure $make, ?\Closure $repositories = null): void
    {
        self::$reader = new self($make, $repositories);
    }

    public static function reader(): self
    {
        return self::$reader ??= new self(static fn (string $type): object => new $type());
    }

    /**
     * @param \Closure(class-string): object  $make         an application's type by its class (its constructor's services)
     * @param (\Closure(): Repositories)|null $repositories the records a select lists at once (criteria, order_by, group_by), asked then
     */
    public function __construct(private readonly \Closure $make, private readonly ?\Closure $repositories = null)
    {
    }

    /**
     * @param class-string         $type    the form type (App\Form\ContactType)
     * @param array<string, mixed> $options the form's options, as createForm() takes them
     */
    public function definition(string $type, array $options = []): FormDefinition
    {
        return $this->read($type, $options)[0];
    }

    /**
     * The form, and what of it was not read (one line each: the field, what, why).
     *
     * @param class-string         $type
     * @param array<string, mixed> $options
     *
     * @return array{0: FormDefinition, 1: list<string>}
     */
    public function read(string $type, array $options = []): array
    {
        $chain = $this->chain($type);
        $instance = $chain[array_key_last($chain)];
        if (null !== $this->kind($instance->getParent(), false)) {
            throw new \LogicException(\sprintf('%s is a field type (its parent is %s), not a form: it is read as a field of the form that adds it.', $type, $instance->getParent()));
        }
        $unread = [];
        $form = $this->build($chain, $this->resolve($chain, $options, self::FORM_OPTIONS), $instance->getBlockPrefix(), $type, $unread);

        return [$form, $unread];
    }

    /**
     * A form from its type's chain and resolved options: its settings, then each field its buildForm() adds.
     *
     * @param non-empty-list<FormTypeInterface> $chain
     * @param array<string, mixed>              $options
     * @param list<string>                      $unread
     */
    private function build(array $chain, array $options, string $name, string $type, array &$unread, string $prefix = ''): FormDefinition
    {
        $factory = self::recorder();
        $builder = new FormBuilder($name, \is_string($options['data_class']) ? $options['data_class'] : null, new EventDispatcher(), $factory, $options);
        $builder->setCompound(true);
        foreach ($chain as $link) {
            $link->buildForm($builder, $options);
        }

        $form = FormDefinition::new($builder->getName())
            ->setDataClass($builder->getDataClass())
            ->setMethod(\is_string($options['method']) ? $options['method'] : FormDefinition::METHOD_POST)
            ->setCsrf((bool) $options['csrf_protection'])
            ->setTranslationDomain(\is_string($options['translation_domain']) ? $options['translation_domain'] : null)
            ->setAttr(self::map($options['attr']));
        if (\is_string($options['csrf_token_id'])) {
            $form->setIntent($options['csrf_token_id']);
        }
        if (\is_string($options['action']) && '' !== $options['action']) {
            $form->setTarget($options['action']);
        }
        if (false !== $options['guard'] && null !== $options['guard']) {
            $form->setGuard(\is_array($options['guard']) ? self::map($options['guard']) : (bool) $options['guard']);
        }
        if (\is_array($options['validation_groups'])) {
            $form->setValidationGroups(array_values(array_map(strval(...), array_filter($options['validation_groups'], \is_scalar(...)))));
        }
        if (\is_string($options['validation_entity']) && class_exists($options['validation_entity'])) {
            $form->setValidationEntity($options['validation_entity']);
        }
        if ($builder->getEventDispatcher()->hasListeners()) {
            $unread[] = \sprintf('%s: its form events (%s) are Symfony\'s; the form is read without them', '' !== $prefix ? rtrim($prefix, '.') : $type, implode(', ', array_keys($builder->getEventDispatcher()->getListeners())));
        }

        foreach ($builder->all() as $childName => $child) {
            $this->field($form, (string) $childName, $child, $unread, $prefix);
        }

        return $form;
    }

    /**
     * The kind of a form type's class: omnibridge/symfony's own table where it is installed, else by its name; a
     * type of the application (a select of its own) by its parent's. Null for a form (Symfony's FormType, a type
     * that builds fields).
     */
    public function kind(?string $type, bool $parents = true): ?string
    {
        if (null === $type || FormType::class === ltrim($type, '\\')) {
            return null;
        }
        $type = ltrim($type, '\\');
        if (null !== $kind = self::KINDS[$type] ?? Kinds::kind($type, 'symfony')) {
            return $kind;
        }
        if (str_starts_with($type, self::CORE) || str_starts_with($type, self::FIELDS)) {
            $name = substr($type, strrpos($type, '\\') + 1, -\strlen('Type'));
            $field = 'Base\Field\\'.$name.'Field';
            $descriptor = class_exists($field) && is_subclass_of($field, \Base\Field\FieldInterface::class) ? $field::new('field') : null;
            if ($descriptor instanceof \Base\Field\FieldInterface && \is_string($kind = $descriptor->getAsDto()->getKind())) {
                return $kind;
            }
            $kind = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

            return \in_array($kind, Kind::all(), true) ? $kind : null;
        }
        if ($parents && class_exists($type) && is_subclass_of($type, FormTypeInterface::class)) {
            $instance = ($this->make)($type);

            return $instance instanceof FormTypeInterface ? $this->kind($instance->getParent()) : null;
        }

        return null;
    }

    /**
     * A field recorded: its kind, its settings, its options; a button an action of the form.
     *
     * @param list<string> $unread
     */
    private function field(FormDefinition $form, string $name, FormBuilderInterface $child, array &$unread, string $prefix = ''): void
    {
        $type = $child->getAttribute(self::TYPE);
        $options = self::map($child->getAttribute(self::OPTIONS));
        if (!\is_string($type)) {
            $unread[] = \sprintf('%s: no type given (Symfony guesses one from the data class); not read', $prefix.$name);

            return;
        }
        // An application's own field type (a select of its own): its options under the given ones, its parent's kind.
        if (!str_starts_with($type, self::CORE) && !str_starts_with($type, self::FIELDS) && class_exists($type) && is_subclass_of($type, FormTypeInterface::class)) {
            $chain = $this->chain($type);
            if (null === $this->kind($chain[0]->getParent(), false)) {
                // A form of its own (PrivacyType): a nested form, its fields read as the root's are.
                $this->nested($form, $name, $chain, $options, $unread, $prefix);

                return;
            }
            $options = $this->resolve($chain, $options, []);
            $type = (string) $chain[0]->getParent();
        }
        $kind = $this->kind($type);
        if (null === $kind) {
            $unread[] = \sprintf('%s: its type %s is no kind of omnibase\'s (%s); not read', $prefix.$name, $type, class_exists($type) ? 'a form of its own' : 'not installed here');

            return;
        }
        if ([] !== $child->getModelTransformers() || [] !== $child->getViewTransformers()) {
            $unread[] = \sprintf('%s: its data transformers are Symfony\'s; the field is read without them', $prefix.$name);
        }
        if ($child->getEventDispatcher()->hasListeners()) {
            $unread[] = \sprintf('%s: its form events are Symfony\'s; the field is read without them', $prefix.$name);
        }

        $label = $options['label'] ?? null;
        $label = false === $label || \is_string($label) ? $label : null;
        if (Kind::SUBMIT === $kind || Kind::BUTTON === $kind) {
            $attr = self::map($options['attr'] ?? []);
            unset($options['label'], $options['attr']);
            if ($type === self::CORE.'ResetType') {
                $options['type'] = 'reset';
            }
            $form->addAction($name, \is_string($label) ? $label : null, $kind, $attr, $options);

            return;
        }

        $constraints = [];
        foreach ((array) ($options['constraints'] ?? []) as $constraint) {
            $kept = \is_object($constraint) ? AttributeConstraints::ofObject($constraint) : null;
            null !== $kept ? $constraints[] = $kept : $unread[] = \sprintf('%s: its constraint %s is read by no validator here; not checked', $prefix.$name, get_debug_type($constraint));
        }
        unset($options['constraints']);
        if (Kind::REPEATED === $kind && \is_string($options['type'] ?? null)) {
            $options['type'] = $this->kind($options['type']) ?? Kind::TEXT;
        }
        // A select of records listed at once (criteria, order_by, group_by), as omnibridge/symfony's SelectType lists them.
        if (Kind::CHOICE === $kind && null === ($options['choices'] ?? null) && null !== $this->repositories
            && (isset($options['criteria']) || isset($options['order_by']) || isset($options['group_by']))) {
            $repositories = $this->repositories;
            $given = $options;
            $options['choices'] = static fn (): array => \Base\Field\Behaviour\Select::records($repositories(), $given) ?? [];
        }

        $field = FormDefinition::field($name, $kind, \is_string($label) ? $label : null)
            ->setFormTypeOptions($options)
            ->setRequired((bool) ($options['required'] ?? true))
            ->setConstraints($constraints);
        if (false === $label) {
            $field->setLabel(false);
        }
        if (\array_key_exists('disabled', $options)) {
            $field->setDisabled((bool) $options['disabled']);
        }
        if (\is_string($options['help'] ?? null)) {
            $field->setHelp($options['help']);
        }
        if (\array_key_exists('empty_data', $options) && !$options['empty_data'] instanceof \Closure) {
            $field->setEmptyData($options['empty_data']);
        }
        if (false === ($options['mapped'] ?? true)) {
            $field->setVirtual(true);
        }
        $form->add($field);
    }

    /**
     * A field whose type builds a form of its own (Kind::FORM): its chain's options (the form's own under the
     * given ones), its fields read as a form's, what its type gives its view (buildView(): PrivacyType's notice).
     *
     * @param non-empty-list<FormTypeInterface> $chain
     * @param array<string, mixed>              $given
     * @param list<string>                      $unread
     */
    private function nested(FormDefinition $form, string $name, array $chain, array $given, array &$unread, string $prefix): void
    {
        $options = $this->resolve($chain, $given, self::FORM_OPTIONS + ['mapped' => true]);
        $nested = $this->build($chain, $options + ['csrf_protection' => false], $name, $chain[array_key_last($chain)]::class, $unread, $prefix.$name.'.');
        $nested->setCsrf(false);
        $view = new \Symfony\Component\Form\FormView();
        $config = new \Symfony\Component\Form\FormConfigBuilder($name, null, new EventDispatcher(), $options);
        $config->setCompound(true)->setDataMapper(new \Symfony\Component\Form\Extension\Core\DataMapper\DataMapper());
        $symfony = new \Symfony\Component\Form\Form($config->getFormConfig());
        foreach ($chain as $link) {
            $link->buildView($view, $symfony, $options);
        }
        $label = $options['label'];
        $field = FormDefinition::field($name, Kind::FORM, \is_string($label) ? $label : null)
            ->setFormTypeOptions([
                'form' => $nested, 'view' => $view->vars, 'block_prefix' => $chain[array_key_last($chain)]->getBlockPrefix(),
                'attr' => self::map($options['attr']), 'row_attr' => self::map($options['row_attr']),
                'mapped' => (bool) $options['mapped'], 'error_bubbling' => (bool) $options['error_bubbling'],
            ])
            ->setRequired((bool) $options['required']);
        if (false === $label) {
            $field->setLabel(false);
        }
        if (\is_string($options['help'])) {
            $field->setHelp($options['help']);
        }
        if (false === $options['mapped']) {
            $field->setVirtual(true);
        }
        $form->add($field);
    }

    /**
     * The type and its parents, the farthest first, up to Symfony's FormType (excluded) or a type that is no class of
     * the application (one of omnibase's or Symfony's field types: its kind is read, not its code).
     *
     * @param class-string $type
     *
     * @return non-empty-list<FormTypeInterface>
     */
    private function chain(string $type): array
    {
        $chain = [];
        for ($current = $type; null !== $current && FormType::class !== ltrim($current, '\\'); $current = $instance->getParent()) {
            if (str_starts_with(ltrim($current, '\\'), self::CORE) || str_starts_with(ltrim($current, '\\'), self::FIELDS) || !class_exists($current)) {
                break;
            }
            $instance = ($this->make)($current);
            if (!$instance instanceof FormTypeInterface) {
                throw new \InvalidArgumentException(\sprintf('%s is no form type.', $current));
            }
            array_unshift($chain, $instance);
        }

        return [] !== $chain ? $chain : throw new \InvalidArgumentException(\sprintf('%s is none of the application\'s form types.', $type));
    }

    /**
     * @param list<FormTypeInterface> $chain
     * @param array<string, mixed>    $given
     * @param array<string, mixed>    $defaults
     *
     * @return array<string, mixed>
     */
    private function resolve(array $chain, array $given, array $defaults): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults($defaults);
        foreach ($chain as $link) {
            $link->configureOptions($resolver);
        }
        // An option the type does not declare is its parent's (a field type's of omnibase): kept as given.
        $resolver->setDefined(array_keys($given));

        return self::map($resolver->resolve($given));
    }

    /** @return array<string, mixed> an option's array by name (anything else: none) */
    private static function map(mixed $value): array
    {
        $map = [];
        foreach (\is_array($value) ? $value : [] as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /** A form factory that builds nothing: each field added to a form being read is a builder holding its type and options. */
    private static function recorder(): FormFactoryInterface
    {
        return new class implements FormFactoryInterface {
            /** @param array<string, mixed> $options */
            public function create(string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
            {
                throw new \LogicException('A form read is never built.');
            }

            /** @param array<string, mixed> $options */
            public function createNamed(string $name, string $type = FormType::class, mixed $data = null, array $options = []): FormInterface
            {
                throw new \LogicException('A form read is never built.');
            }

            /** @param array<string, mixed> $options */
            public function createForProperty(string $class, string $property, mixed $data = null, array $options = []): FormInterface
            {
                throw new \LogicException('A form read is never built.');
            }

            /** @param array<string, mixed> $options */
            public function createBuilder(string $type = FormType::class, mixed $data = null, array $options = []): FormBuilderInterface
            {
                return $this->createNamedBuilder('form', $type, $data, $options);
            }

            /** @param array<string, mixed> $options */
            public function createNamedBuilder(string $name, string $type = FormType::class, mixed $data = null, array $options = []): FormBuilderInterface
            {
                return $this->record($name, $type, $options);
            }

            /** @param array<string, mixed> $options */
            public function createBuilderForProperty(string $class, string $property, mixed $data = null, array $options = []): FormBuilderInterface
            {
                return $this->record($property, null, $options);
            }

            /** @param array<string, mixed> $options */
            private function record(string $name, ?string $type, array $options): FormBuilderInterface
            {
                $builder = new FormBuilder($name, null, new EventDispatcher(), $this, $options);
                $builder->setAttribute(TypeReader::TYPE, $type)->setAttribute(TypeReader::OPTIONS, $options);

                return $builder;
            }
        };
    }
}
