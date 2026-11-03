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

What a site's form needs beyond fields, each read by every host:

- `FormDefinition::field('email', Kind::EMAIL)`: a bare field, none of what a descriptor class adds for the back office;
- `setFormTypeOption('data', true)`: a value fixed whatever the data; `'choices' => $repository->subscribable(...)`: a callable, asked when
  read; a list of records as choices (each says its own name, its id the value);
- `addRewrite(fn (array $sent): array => ...)`: what was sent rewritten before it is read (Symfony's PRE_SUBMIT);
- `Kind::REPEATED`: one value typed twice, option `type` the kind of the two inputs, the mismatch said under the first;
- `addAction('submit', null, Kind::BUTTON, [], ['confirmation' => true, 'type' => 'submit'])`: ButtonType's modal;
- `setGuard(['action' => 'signup'])`: `FormGuard`'s trap, stamp, lists, under Symfony by the option `guard`, elsewhere by `FormProcessor`;
- `setValidationGroups(['new'])`, `setValidationEntity(User::class, ['username'])`: the record's class-level rules on the form's values, its
  refusals the form's own but for the fields named. `FormSubmission::isTaken('email')` tells a page which.

A model's or entity's rules stay on one line: `Omnibridge\Validation\AttributeConstraints` reads symfony/validator's attributes by name, their
class never loaded, as the kept constraints. `Unique` asks `Base\Form\Definition\UniqueLookup`, over `Repositories`, on both hosts.

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

### What a host does once a form holds (step F5c)

- An empty string a definition gives as its empty data (`setEmptyData('')`) stays a string, as Symfony's
  `TextType` keeps it, whether the field came empty or not at all (Laravel sends an empty field as null).
- What the binder could not read ("The values do not match.") is said in the page's language: the
  `validators` domain, when the processor has `Translations`.
- A record's uniqueness declared without its class (`#[UniqueEntity(fields: ['email'])]` on the entity) is
  looked up among the records of the form's validation entity.
- A refusal the code that keeps the data gives (a spam, a record not found) is added to the submission:
  `$submission->withError($message, $field)`, a new submission, as Symfony's `addError()`.
- A record the form has just written into is held to its class's rules before it is kept:
  `RecordRules::violations($validation, $record, $groups)` - its uniqueness above all, the record itself not
  counted as taken. omnibase's profile editor does it on both hosts: a name another account has is said
  under its field, the account read again as it is stored (it answered 500 and signed the session out).
- A sign-up once its form holds is `Base\Auth\Registration::register()` on every host (approved, its
  password, named after its address when its class has a username left empty, kept); the host signs it in.
- A rule of the data on a field with `error_bubbling` is the form's error, as a rule of the field's own.
- The sign-up's terms of use are checked by the server (`agreeTerms`, `user.agree_terms`), in the default
  group and the sign-up's `new`.

### The Symfony adapter (omnibridge/symfony)

The Symfony half of the form layer lives in `Omnibridge\Symfony\Omnibase\` since F5b, each class under its
former path: `Field\Type\*`, `Form\Type\*`, `Form\Extension\*`, `Form\Transformer\*`, `Form\Traits\*`,
`Form\Common\{AbstractType, FormTypeInterface}`, `Form\{FormFactory, FormProxy, FormProcessor}` and their
interfaces (78 classes). Their `Base\` names keep working until 6.0: `src/Resources/former-names.symfony.php`.
Sites keep `createForm()`, their themes and `Base\Field\Type\X::class` everywhere. Because `::class` of a former
name is a plain string while Symfony's registry asks a type's extensions by its declared name,
`FormerFormTypesPass` gives the type locator both names and files the extensions under the declared one. A
`PreloadedExtension` (a hand-built factory, in a test) knows the declared name only: ask it by the new one.
`App\Form\` stubs and aliases read both folders (`FormStub`, `BaseBundle::symfonyFormDir()`).

`Omnibridge\Symfony\Omnibase\Form\DefinitionForms` makes a Symfony form of a definition - types by kind, options
as the admin maps a CRUD field's, kept constraints translated (`SymfonyConstraints`), actions as buttons,
`getHostOptions()` winning - and `Omnibridge\Contract\Validation` is `SymfonyValidation` over symfony/validator.
A site's form stays a type, what Symfony asks for: `Omnibridge\Symfony\Omnibase\Form\DefinitionType` builds it from a definition
(`definition(array $options)`), so `FormProxy::createProcessor()`, its name and its block prefix do not change.
The generic renderers - the Twig theme (omnibridge/twig),
the Blade theme (omnibridge/laravel), React - read `FormView`. The whole layer loads under
`bare --strict` (`BareTest::testTheFormLayerWorksWithoutAFormLibrary`).

### Every kind on every host: behaviours, coverage, conformance (step F5d)

What a rich field does - a date picker, a slug made from another field, a switch that asks first, a
select2 searched remotely, a colour, a localized number, a password typed twice - lived only in its
Symfony type, and a host that did not know a kind drew a plain input in silence. Three pieces close it.

**Behaviours.** omnisense/omnibridge's `Omnibridge\Form\FieldBehaviour` is the contract one behaviour
answers whatever host draws it: `options()` (the given ones over its defaults, normalized), `vars()` (what
its widget reads, plain data: `datetimepicker`, `select2`, data attributes under `attr`), `transform()` and
`reverse()` (the value shown, the value read back; `\UnexpectedValueException` for a value it cannot read),
`assets()` (the script's entry, the prefix of the attributes its controller reads, `FieldAssets`),
`endpoints()` (the host's routes it calls, `Endpoint`: a select's remote search). The core's
`Base\Field\Behaviour\*` hold the logic the Symfony types held, and `Behaviours::of($kind)` gives them:

| Kind | Behaviour | Widget |
|---|---|---|
| `datetime_picker` | `DateTimePicker`: ICU `format`, flatpickr's settings merged key by key | `datetimepicker` |
| `slug` | `Slug`: separator, kept characters, strict, lock, the `target` path | `slug` |
| `boolean` | `Toggle`: switch, confirmations, the toggle route | `boolean` |
| `color` | `JsColor`: `#RRGGBBAA` shown, as short as written kept | `jscolor` |
| `color_picker` | `ColorPickr`: Pickr's settings, the alpha completed | `color` |
| `number` | `Number`: the page's language (48,857), divisor, prefix, suffix, steppers | `number` |
| `password` | `Password`: `plain` and `plain_repeater`, strength bounds | `password2` |
| `choice`, `role`, `state`, `gender`, `currency`, `country` | `Select` (`CountrySelect`): select2's settings (`Select::select2()`), an enum's choices (`Choices::of()`), a record's remote search (`ux_autocomplete`) | `select2` |
| `icon` | `Icon`: the icon provider's search (`ux_autocomplete_icons`) | `select2` |
| `locale` | `ChoiceList`: Intl's locales | a plain select |

The Symfony types read them (`DateTimePickerType`, `SlugType`, `BooleanType`, `ColorType`, `ColorPickerType`,
`NumberType`, `SelectType`'s select2 settings): the 13 forms and the back office's pages draw the same markup.
The neutral `FormBinder` reads through a behaviour first (the field's options completed with its defaults),
then the kind's generic rule; the neutral `FormView` gives a rich field its `widget`, `webpack_entry`, settings
and `endpoints` (the address of each route on this host, null where it has none). A select2 is its form's
`choice` part on both hosts (`name[value][choice]`), as `SelectType` names it. A field whose host type is
named (`setFormType()`) has no behaviour: its type decides.

**Coverage.** Each host declares every kind, implemented or `Coverage::unsupported('why')` (omnisense/omnibridge's
`Omnibridge\Form\Coverage`): Symfony's `SymfonyKinds::COVERAGE`, Laravel's `LaravelKinds::COVERAGE`, read by
`Kinds::coverage($host)` (completed by `Kinds::declare()`); `Kinds::faults($host)` names a kind left undeclared,
an application's own kinds included. A kind a host declares unsupported is refused aloud when drawn
(`Omnibridge\Form\UnsupportedKind`): `DefinitionForms` for Symfony (`html`), omnibridge/laravel's `FormRenderer`
for Laravel, which also refuses a select whose remote search route it does not have (an application without
omnibase's media, through which the search's payload goes). A field that names its host
type (`setFormType()`) and keeps a kind is drawn from its generic view by the generic renderers (`explicit` in its
vars); one with no kind at all (`custom`) is refused there. lesringostars-react declares its own coverage
(`src/screens/kinds.json`) against `src/Resources/field-kinds.json`, the core's list of kinds as JSON.

The record's mapping completes what a field leaves out, as the Symfony types guess it from Doctrine
(`Base\Field\Behaviour\Guess`, on the unit of work's entity shapes, aliases resolved): a select's class (an
association's target, an enumeration column's class) and whether it holds several, its sorting (`#[OrderColumn]`),
a slug's `required` (its column not nullable). A select2's entries are omnibase's `Autocomplete::resolve()`'s (an
enumeration's word and icon) where the host gives omnibase's translator; a country's carries its flag.

**The remote search.** A select of records (an association picked, `class` a record's) and an icon picker
search their entries on the host: `Base\Field\Search\RemoteSearch`, the same on every host, at the routes of
`RemoteSearch::ROUTES` - `ux_autocomplete` (`/ux/autocomplete/{data}`: a class's records through its repository's
`cacheByInstanceOf()`, paged by the Paginator, named by `Autocomplete`, kept when their text holds the term; an
enumeration's values) and `ux_autocomplete_icons` (`/ux/autocomplete/{provider}/{pageSize}/{data}`: an icon adapter's
icons, through `Base\Service\Icons`). `data` is `RemoteSearch::data()`: the class, the fields, the filters
(`RemoteSearch::filters()`), the case, whether the entries are HTML and show faces, and the visitor's token of
intent `select2` (the `Tokens` contract), obfuscated in its short form; the search refuses another token, an unknown
payload, a method the environment does not take (GET and POST in debug, POST otherwise: `autocomplete_type`).
Symfony's `AutocompleteController` and omnibridge/laravel's `SearchController` hand it the request's method, query
and body and send its `Answer` as JSON. `SelectType` makes its `data` with it; the neutral `FormView` gives the
`search` endpoint its `data` and starts select2 with the entries chosen (the records found by their ids, an icon
named by its adapter's list), so both hosts draw the same picker.

**Uploads.** A file field (`file`, `image`, `avatar`, `audio`, `video`) is `Base\Field\Behaviour\Upload` on every host:
the defaults and normalizers of omnibase's `FileType` and its children, and what their blocks show of the record's files -
the stored files' names (the hidden input `[file]`), their public addresses, thumbnails and previews, the download links, the
largest size and the types accepted (the record's `#[Uploader]` and `#[File]`), an image's thumbnail, modal and cropper. The
file to send is `[raw]` (or `[url]` with `allow_url`); a field of several files with a dropzone sends each file as it is dropped
to `Base\Field\Upload\Dropzone`'s `ux_dropzone`, which keeps it under a uuid in the cache folder's `dropzone/` until the form
is sent (`ux_dropzone_preview` shows it again, `ux_dropzone_delete` removes it; its payload carries the visitor's token of intent
`dropzone`). What is sent back is read as `FileType` reads it (`Upload::reverse()`): the file sent, else the one fetched, else
what `[file]` names - the dropzone's kept files, or a stored file's name -; a compound sent empty removes the file, a field not
sent keeps it. The record's `#[Uploader]` stores the file when the record is flushed. Symfony's types and its
`DropzoneController` ask the behaviour and the service; the neutral `FormView` gives the behaviour the record it edits.

The file sent is refused when the form is validated, as `FileType`'s `File` constraint on `[raw]` refuses it, on every host
(`Upload::refusal()`, which the neutral `FormBinder` applies): a type its `mime_types` do not match (`file.mime_type`), else a
size over its ceiling - PHP's upload limit, narrowed by its `class`' `#[Uploader]` and `#[File]` and by `max_size` -
(`file.max_size`); the field's error is the constraint's message (the `validators` catalogue), the data is left untouched, the
record's `#[Uploader]` is never reached. `Base\Validator\Constraints\File` reads an integer as bytes: `FileType`'s ceiling,
which it read as bits until then (32 MB refused a file over 4 MB).

**Records made from uploads.** An `association_file` field (a gallery's photos, a photo's picture: `AssociationFileField`) is
`Base\Field\Behaviour\AssociationFile` on every host: a compound holding one upload field (`file`, `image` or `avatar`, by its
`form_type`) named by the first part of `entity_file`, the path from a related record to its file (`image.source`). The upload
field shows the related records' files (`files()`) and carries each stored file's name by its record's id (`entries()`,
`data-entry-id`); several records when the association is to-many (the entity's shape), in order where it is kept so. What is sent
is made into the related records (`records()`): a file sent (or kept by the dropzone) a new record - hydrated, then given
`entity_data`, a callable or values, else its file -, a stored file's name its record kept, a name left out its record left; the
neutral `FormBinder` writes them into the record (`bind()`: a to-many collection replaced, an inverse side's records told their
owner, from the shape), Symfony's `AssociationFileType` into its `PersistentCollection` as before. The admin's edit link of a
record (`href`) stays the Symfony back office's.

**Editors.** A text written in Editor.js (`editor`) or in Quill (`wysiwyg`) is kept in a hidden input on every host, its
script's element beside it. `Base\Field\Behaviour\Wysiwyg` gives Quill's settings (`wysiwyg`: theme, toolbar, placeholder,
height); `Base\Field\Behaviour\Editor` gives Editor.js's (`editor`: the placeholder and, when `collab_autosave` or `collab_live`
is asked and the form's record is stored, its field's room - token, version hash, the account signed in; `collab_live` only
where a relay is configured), the value with each picture's address made from its stored origin, and the five addresses of
the editor's routes (`uploadByFile`, `uploadByUrl`, `endpointByUser`, `endpointByThread`, `endpointByKeyword`); a document
sent without a block is read back as no text. Both widgets' ids have their dashes made underscores (a translation's `fr-FR`
part). The routes are `Base\Field\Editor\EditorJs`'s (`ROUTES`, the `ux_editorjs_*` names): the mentions with the editor's
token (`editorjs`), five by page; a picture sent or fetched, stored in the editor's storage (`base.twig.editor.storage`); the
relay's ticket and one field's autosave with its room's own token (a stale version refused, 409). Symfony's
`EditorController` and omnibridge/laravel's answer them through it; the neutral `FormView` gives the behaviour the record and
the field's name (`editor_record`, `editor_field`).

**Embedded forms, lists and attributes.** A related record's form (`association`, an `AssociationField` that embeds) is
drawn on every host as `AssociationType` builds it: one record, its fields and its `_index`; several (a to-many association),
the collection `_collection` whose entries, by their index in the association, are each one record's form, with the
prototype of a new one where entries are added (`__prototype__`) and the window of a capped collection (`max_entries`,
`entry_offset`; the indexes drawn sent back as `_rendered`). `Base\Field\Behaviour\Association` holds what the type decides
(several records are never required, and nothing inside them; entries added and deleted only inside a group; the
collection's and an entry's options) and its fields (`RecordFields`, the field's `fields`, `autoload`, `excluded_fields`), the
association read through its alias (#[Alias]: a comment's replies); the neutral `FormBinder` binds each entry sent into its
record, a new one from the prototype told its owner, one not sent removed where the field deletes (an entry's own collection
of records is not written from it, as `AssociationType` leaves it). A list (`collection`, `array`) is
`Base\Field\Behaviour\Collection`: `CollectionType`'s and `ArrayType`'s defaults (a pattern's `{n}` counted as a fixed
length that neither adds nor deletes), the window, the empty markup, what is sent read as the list kept (an associative array's
`{key, value}` entries as key => value). A record's attributes (`attribute`) are `Base\Field\Behaviour\Attribute`: the
attribute's class and its adapters', the select of adapters (searched remotely, `filter` narrowing them), each attribute's part
named by its adapter's code and id; bound, an attribute kept for each adapter still chosen, a new one for an adapter newly
chosen. A translated attribute's value (its `intl` part, a `TranslationType` of several records) is drawn by Symfony's forms
only: omnibridge/laravel refuses such a field aloud once it holds one. Symfony's `AssociationType`, `CollectionType`,
`ArrayType` and `AttributeType` ask these helpers; a list of records written into the collection a record holds is written
in place by the neutral binder (the records no longer chosen out, the new ones in), as Symfony's forms do through adders.

**Translations.** A translations field (`translation`) is one part per language, each the translation entity's
fields, on every host: `Base\Field\Behaviour\Translation` holds what `TranslationType` decides (the languages drawn -
every offered one, or for `single_locale` the default one, the record's one language when it has exactly one -; which
part is required - the default and `required_locales`; `locale_options` per language; a callable `required`), and
Symfony's type asks it. The translation entity's fields are `Base\Field\Behaviour\RecordFields::of()`, what
`ClassMetadataManipulator::getFields()` lists from Doctrine read from the entity's shape: the `fields` named first, then
the other columns unless `autoload` is off, `id`, `translatable`, `locale` and `excluded_fields` left out, each with
the kind its column says (string a text, text a textarea, json or array a select of free tags, integer a number,
datetime a datetime picker, an enumeration a select of its values) or the one its `form_type` names. The neutral
`FormView` gives such a field a part per language (`form[translations][fr-FR][title]`, ids
`form_translations_fr-FR_title`), the record's values in each, and `TranslationType`'s vars (`nonempty_locales`,
`compatible_locale`...); `FormBinder` writes what is sent into the record's translations (`translate()`), a language
left empty is not kept. omnibridge/laravel draws it as `translatable_widget` does - a tab per language -, and refuses it
aloud when one of its fields is a kind it does not draw.

**Conformance.** `Base\Field\Conformance\Suite` runs `Samples::all()` (one sample per kind of `Kind::all()`, with
Symfony's values: the reference) on a `Host` - its name, `render()`, `submit()`: every kind declared, each
implemented one drawn under its name, its value shown, what is sent bound to the same value, a value it cannot
read refused; each unsupported one refused. A kind added to `Kind` without a sample fails `Samples::all()`;
without a host's word, the host's suite. The hosts' suites: `tests/Field/SymfonyConformanceTest` (the harness,
`Omnibridge\Symfony\Omnibase\Form\Conformance\SymfonyHost`), omnibridge/laravel's `tests/FieldConformanceTest`
(`Omnibridge\Laravel\Form\Conformance\LaravelHost`), and an application's own.
