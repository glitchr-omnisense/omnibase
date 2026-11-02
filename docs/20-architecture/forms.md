---
title: Forms
order: 50
---

# Forms

omnibase's form theme (`templates/form/form_div_layout.html.twig`, added to
`twig.form_themes` by the bundle) renders every form: the `form2` blocks, the
label strip, the custom field types.

## Labels, help and placeholders are translation keys

Texts are translated by `Base\Service\Translator`. A key may name its domain
(`@mailbox.form.subject`), or be looked up in the field's `translation_domain`.

Every field has the domain **`fields`** by default
(`Base\Form\Extension\FormTypeExtension`), where omnibase's own field types
keep their words. A form type that declares its own domain is followed by its
fields:

```php
public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->setDefaults(['translation_domain' => 'mailbox']);
}

public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder->add('subject', TextType::class, [
        'label' => 'form.subject',                         // mailbox.form.subject
        'help' => 'form.subject_help',
        'attr' => ['placeholder' => 'form.subject_placeholder'],
    ]);
}
```

The rule, applied when the view is finished
(`FormTypeExtension::inheritTranslationDomain()`): when none of a field's
label, help and placeholder exists in the field's own domain and one of them
exists in the domain of a form above it, the field is rendered in that
domain. Otherwise nothing changes: a field whose texts are in `fields` stays
there, a key that names its domain is translated in it, and
`'translation_domain' => false` prints the texts as written.

(A field used to ignore its form's domain: the key was looked up in `fields`,
found nothing, and the label was printed empty. The bundles named the domain
in every key to go around it; they no longer need to.)

## A PHP enum in a select

`Base\Field\Type\SelectType` bound to a property that holds a PHP enum finds
its choices by itself - the cases - and hands the case back to the record:

```php
$builder->add('state', SelectType::class);                                   // Doctrine enumType:, or a property typed with the enum
$builder->add('states', SelectType::class, ['class' => CommentState::class, 'multiple' => true]);
```

The select holds a case by its backed value (its name when the enum is not
backed, `Base\Form\Common\NativeEnum::id()`). A case is labelled, in this
order: by itself when the enum implements Symfony's `TranslatableInterface`;
by the key `<short class name in snake case>.<value>` of the `enums` domain
(`comment_state.pending`); by its name made readable. A value that names no
case is an invalid choice, and the record keeps what it had.

A field that gives `choices` (or a `choice_loader`) keeps them as they are:
the enum is not guessed over them, and `['Label' => 'value']` shows the label
(translated when it is a key such as `@agenda.role.soloist`).

`ClassMetadataManipulator::getFields()` - what `AssociationType` builds an
embedded form from - maps such a column to a `SelectType` as well; it used to
be a `TextType`, which cannot print a case.

## An entity in a select

`SelectType` with an entity `class` (or bound to a Doctrine association) is
fed by the autocompletion and loads what was chosen from the class's
repository: `cacheById()` on omnibase's repositories, `find()` / `findBy()`
on a plain Doctrine one.

`AssociationType` embeds the related record's own form, always. The back
office decides, for an `AssociationField`, between that form and a picker:
see omnibase/admin's `docs/crud-fields.md`.

## A select works before its script does

`SelectType` is drawn by select2, but the `<select>` the server prints is a
whole one: on a page whose script did not run - blocked, failed, not loaded
yet - it can be read, changed and sent.

- **Its options.** The choices of a static list or of an enum, groups as
  `<optgroup>`, with the labels select2 shows; for an autocompleted list, the
  records already chosen. select2 empties the select before filling it from
  its own data, as it always did: nothing is listed twice.
- **Its selection.** The record's value is the `selected` option - also in
  the lists that printed their options already (a `choice_loader`:
  `CurrencyType`), where none was selected and the browser sent the first of
  the list in place of the record's currency.
- **An empty first option** in a select of one value, carrying the
  placeholder: without it the browser picks the first choice for a record
  that has none. It is the option select2 itself asks for to show a
  placeholder.

(The select was printed with no option at all and filled by select2 alone:
without the script a product's availability arrived null.)

### `required` is checked by the server

A required `SelectType` sent empty is an error of the form - "This value
should not be blank.", in the visitor's language (`validators` domain) - on
the field itself, where its row prints it. The browser's `required`
attribute was the only check, and the back office's forms are `novalidate`.

The empty value is not written into the record, which keeps what it had: the
field fails as a transformation does (it is not synchronized), so a setter
that takes no null - `setStatus(PublicationStatus $status)` - is not called
with one. That call answered 500 ("Expected argument of type ..., null
given") in place of the form's error.

- `required` is what the field says, `true` unless said otherwise
  (`'required' => false`, `->setRequired(false)` on a CRUD field): a select
  on a nullable property that may stay empty has to say so.
- A list of several may be empty, as before, unless
  `'required_when_multiple' => true`.
- A disabled field is not asked.

The field's errors no longer go up to the form (`error_bubbling` is `false`,
as for Symfony's own compound choice and date fields): a constraint of the
property is printed beside the select too, not at the top of the form.

## The neutral form layer (plan "omnibase agnostic", step F5b)

Every site has forms, and the admin builds its own from the Fields (Marco's answer 4): forms
are not Symfony's alone. The core describes a form without a form library; each host renders
and processes it.

### Field descriptors name a kind

A field descriptor (`TextField::new('title')`, `FieldDescriptor`) names a **kind**
(`Base\Field\Kind`: `text`, `textarea`, `email`, `password`, `integer`, `number`, `money`,
`choice`, `country`, `association`, `date`, `datetime`, `file`, `image`, `collection`, `boolean`,
`editor`...), never a Symfony form type class. Its options are plain data (`getOptions()`,
`getOption('attr.maxlength')`), its constraints plain objects (`addConstraint()`) that the host's
`Omnibridge\Contract\Validation` reads, its label and help translatable keys.

`Base\Field\Kinds` maps a kind to a host's own name for it: Symfony's form type for
`getFormType()` (the map `SymfonyKinds`, read by name: the same class each descriptor named before),
a theme's block for another host. A bundle or an application registers its own kinds:

```php
Kinds::register('symfony', ['signature' => SignatureType::class]);
```

`setFormType()` stays, as the escape hatch of a Symfony-only field: a type a kind maps to sets that
kind too (a `TextField` given `ChoiceType` is a `choice`), another keeps the kind set, or is
`Kind::CUSTOM`.

### A form: FormDefinition

```php
use Base\Form\Definition\FormDefinition;

$form = FormDefinition::new('comment')
    ->add(TextareaField::new('content', 'comment.content')->setRequired(true)->addConstraint(new NotBlank()))
    ->add(BooleanField::new('notify'), 'options')
    ->addAction('submit', 'comment.submit')
    ->setDataClass(Comment::class);

// or the fields a CRUD page shows
$form = FormDefinition::fromFields('crud_form', $controller->configureFields('edit'), 'edit');
```

A definition holds its fields by name, their groups, its actions (the buttons; the one clicked is
the submitted intent), its method and target, the intent of its token (its name by default), the
data class, the translation domain (the host's default when null) and, as an escape hatch, options
only one host reads (`setHostOptions([...], 'symfony')`).

### Binding, view, processing

- `FormBinder` turns what is sent into the data (an array, an entity, a DTO) and the data into the
  values shown, by kind: a trimmed string (a password untrimmed), `''` as null or the empty data, an
  integer, a number, a date (`input`: `datetime`, `datetime_immutable`, `string`), a choice among
  the field's `choices`, an association by its id (`UnitOfWork::reference()`), a file as the host
  gives it, a missing checkbox as false. A value it cannot read is a violation of its field with
  Symfony's own message ("Please enter an integer.") and leaves the data untouched; a disabled field
  keeps the data's value; the data is written only where its value changes (`Properties`: getters,
  setters, public properties, "." paths, without property-info). Transformers are plain callables:
  `withTransformer($kind, $reverse, $transform)`, or a field's custom options
  `FormBinder::OPTION_REVERSE` / `OPTION_TRANSFORM`.
- `FormView` is a plain tree a renderer reads: each view's `vars` (name, full_name, id, kind, type,
  value, label, help, errors, attr, required, disabled, choices as a list of value/label, multiple,
  checked; the root's method, action, valid) named as Symfony's FormView names them, its children
  by name, the token as the hidden child `_token`. `toArray()` (and `json_encode()`) gives the props
  of a React page.
- `FormProcessor` handles a request's part: bind, check the token (`Omnibridge\Contract\Tokens`, the
  definition's intent), validate (`Omnibridge\Contract\Validation`: the data against what its class
  declares, each field's value against the field's constraints), then keep the data
  (`Omnibridge\Persistence\UnitOfWork`) when asked and valid. Each contract is optional. It is in
  `Base\Services::definitions()`, so every host's container gives it.

```php
$submission = $processor->handle($form, $request->request->all('comment') + $request->files->all('comment'), $comment, persist: true);
if ($submission->isValid()) {
    return redirect(...);
}

return view('comment', ['form' => $submission->createView()]);   // the values sent, their errors, a token
```

Symfony keeps its own forms and processing: the adapter builds a Symfony form from a definition
(omnibridge/symfony, F5b's second part). The generic renderers - the Twig theme (omnibridge/twig),
the Blade theme (omnibridge/laravel), React - read `FormView`. The whole layer loads under
`bare --strict` (`BareTest::testTheFormLayerWorksWithoutAFormLibrary`).
