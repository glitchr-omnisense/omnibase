---
title: Form types
order: 50
---

# Form types

The form types and type extensions of the core, with the options each one
declares itself - what `bin/console debug:form` reports for it, the options
of its parent and of the extensions left out. They were read in the core's
harness from the form registry (`$type->getOptionsResolver()` against its
parent's). How labels, help texts and selects behave is in
[Forms](../20-architecture/forms.md); the `guard` option in
[The forms' guard](../20-architecture/guard.md).

## The extensions on every form

Every `FormType` (so every form and field) gets these options:

| Option | Default | Extension | Effect |
|---|---|---|---|
| `form2` | `base.twig.use_form2` | `FormTypeExtension` | The `form2` variant of the theme |
| `easyadmin` | `base.twig.use_ea` | `FormTypeExtension` | The `easyadmin` variant of the theme |
| `form_flow`, `form_flow_id` | `true`, `'_flow_token'` | `FormTypeExtension` | The multi-step flow of a form whose `data_class` is a `FormModelInterface` (the id is then the model's type name) |
| `validation_entity` | `null` | `FormTypeExtension` | The entity a form model is validated as |
| `use_model` | `false` | `FormTypeExtension` | Map the form on a model |
| `translation_domain` | see [Forms](../20-architecture/forms.md) | `FormTypeExtension`, `FormTypeTranslateExtension` | Where the field's texts are translated |
| `bootstrap`, `bootstrap_label` | `base.twig.use_bootstrap`, `true` | `FormTypeBootstrapExtension` | Bootstrap's classes on the row and the label |
| `collab` | `false` | `FormTypeCollabExtension` | `false`, `'autosave'` or `'live'`: presence and autosave on any field (`data-collab-*` attributes; the actions `ux_editorjs_collabTicket` and `ux_editorjs_autosave`) |
| `csrf_field_name` | `'_csrf_token'` | `FormTypeCsrfExtension` | The CSRF field's name |
| `webpack_entry` | `null` | `FormTypeWebpackExtension` | The webpack entry the field's script is in |
| `guard`, `spam_protection` | see the guard's page | `FormTypeGuardExtension` | The forms' guard: trap, delay, lists, captcha, classifier |

`FormTypeCollectionExtension` declares no option.

## Field types (`Base\Field\Type`)

Every one is a service (`bin/console debug:form` lists them under "Service form
types"). The block prefix is the name of its block in the form theme
(`<prefix>_widget`, `<prefix>_row`).

| Type | Parent | Block prefix | Its own options |
|---|---|---|---|
| `ArrayType` | `FormType` | `array` | `length`, `max_entries`, `entry_offset`, `allow_object`, `allow_add`, `allow_delete`, `html`, `href`, `prototype`, `prototype_data`, `prototype_name`, `group`, `checkbox`, `row_group`, `entry_collapsed`, `entry_type`, `entry_label`, `entry_options`, `entry_required`, `delete_empty`, `target`, `associative`, `pattern`, `placeholder` |
| `CollectionType` | `FormType` | `collection2` | the same as `ArrayType` without `target`, `associative`, `pattern`, `placeholder` |
| `AssociationType` | `FormType` | `association` | `class`, `form_type`, `embed`, `autoload`, `href`, `html`, `entry_collapsed`, `max_entries`, `entry_offset`, `entry_label`, `entry_required`, `fields`, `keep_indexes`, `length`, `excluded_fields`, `recursive`, `multiple`, `group`, `row_group`, `allow_add`, `allow_delete`, `allow_entity`, `allow_null` |
| `AssociationFileType` | `FormType` | `associationfile` | `class`, `form_type`, `entity_file`, `entity_data`, `multiple`, `allow_delete`, `allow_delete[confirmation]`, `href`, `max_size`, `max_files`, `mime_types`, `sortable` |
| `AttributeType` | `FormType` | `attribute` | `abstract_class`, `class`, `recursive`, `multiple`, `multivalue`, `filter`, `filter_code`, `sortable`, `allow_add`, `allow_delete` |
| `DiscriminatorType` | `FormType` | `discriminator` | `exclude_root`, `discriminator_autoload` (a choice among the classes of an entity's discriminator map) |
| `TranslationType` | `FormType` | `translatable` | `locale`, `locale_options`, `single_locale`, `default_locale`, `required_locales`, `available_locales`, `autoload`, `fields`, `excluded_fields`, `translation_class`, `translatable_class`, `multiple` |
| `TranslatableType` | `FormType` | `translatable` | `translation_field_name`, `translation_class`, `autoload`, `fields`, `excluded_fields`, `single_locale`, `default_locale`, `required_locales`, `available_locales`, `multiple` |
| `SelectType` | `FormType` | `select2` | `class`, `class_priority`, `disable`, `choices`, `choice_loader`, `choice_thumbnails`, `choice_filter`, `choice_value`, `choice_label`, `select2`, `select2-template`, `select2-templateResult`, `select2-templateSelection`, `theme`, `placeholder`, `capitalize`, `language`, `required_when_multiple`, `multiple`, `multivalue`, `vertical`, `maximum`, `tabulation`, `tags`, `highlight`, `minimumInputLength`, `tokenSeparators`, `closeOnSelect`, `selectOnClose`, `minimumResultsForSearch`, `dropdownCssClass`, `containerCssClass`, `use_html`, `avatar`, `href`, `autocomplete`, `autocomplete_endpoint`, `autocomplete_endpoint_parameters`, `autocomplete_fields`, `autocomplete_data`, `autocomplete_processResults`, `autocomplete_delay`, `autocomplete_type`, `sortable` |
| `CountryType` | `FormType` (extends `SelectType`) | `select2` | `SelectType`'s and `alpha3` |
| `ForexType` | `FormType` (extends `SelectType`) | `select2` | `SelectType`'s and `autocomplete_pagesize` (a currency pair, autocompleted by `ux_autocomplete_forex`) |
| `IconType` | `FormType` (extends `SelectType`) | `select2` | `SelectType`'s and `adapter` (`base.icon_provider.default_adapter`), `autocomplete_pagesize` |
| `CurrencyType` | `SelectType` | `currency` | `display_symbol`, `display_code`, `choice_translation_domain`, `choice_translation_locale` |
| `RouteType` | `SelectType` | `route` | `choice_translation_domain`, `choice_translation_locale` (a choice among the router's routes) |
| `GenderType`, `RoleType`, `StateType` | `SelectType` | `gender`, `role`, `state` | none: `SelectType` on `Gender`, `UserRole`, `ThreadState` (`class`) |
| `QuadrantType` | `FormType` | `quadrant` | `class` (`Quadrant8` by default: a compass direction) |
| `BooleanType` | `CheckboxType` | `boolean` | `confirmation[onCheck]`, `confirmation[onUncheck]`, `toogle_url`, `switch`, `inline` |
| `ButtonType` | `ButtonType` (Symfony's) | `button2` | `confirmation`, `confirmation-bubbleup`, `type` |
| `SubmitType` | `Base\Field\Type\ButtonType` | `submit2` | none |
| `CodeType` | `TextType` | `code` | `length`, `alphabet`, `uppercase`, `submit_on_complete` (a code of known length typed one character per box, carried by one hidden input) |
| `SlugType` | `TextType` | `slug` | `separator`, `keep`, `upper`, `lock`, `strict`, `target` |
| `ColorType` | `TextType` | `jscolor` | `jscolor`, `is_nullable` |
| `ColorPickerType` | `TextType` | `colorpickr` | `pickr`, `is_nullable` |
| `EmojiPickerType` | `TextType` | `emojipickr` | `renderer`, `is_nullable` |
| `DateTimePickerType` | `DateTimeType` | `datetimepicker` | `debug`, `datetimepicker` |
| `NumberType` | `FormType` | `number2` | `scale`, `grouping`, `rounding_mode`, `input`, `html5`, `stepUp`, `stepDown`, `divisor`, `step`, `throttleUp`, `throttleDown`, `inline`, `throttle`, `min`, `max`, `suffix`, `prefix`, `autocomplete`, `keyUp`, `keyDown` |
| `MoneyType` | `FormType` | `money` | `scale`, `grouping`, `rounding_mode`, `divisor`, `currency`, `html5`, `input`, `currency_target`, `currency_label`, `currency_exchange`, `currency_list`, `use_swap` |
| `StockType` | `FormType` | `stock` | `scale`, `grouping`, `rounding_mode`, `input`, `html5`, `allow_infinite`, `stepUp`, `stepDown`, `target` |
| `PasswordType` | `FormType` | `password2` | `inline`, `secure`, `hint`, `revealer`, `repeater`, `autocomplete`, `suggestions`, `min_length`, `min_strength`, `max_strength`, `options`, `options[repeater]` |
| `FileType` | `FormType` | `fileupload` | `class`, `dropzone`, `allow_delete`, `allow_delete[confirmation]`, `allow_cancel[confirmation]`, `multiple`, `clipboard`, `href`, `title`, `allow_url`, `allow_reupload`, `sortable`, `sortable-js`, `lightbox`, `alt`, `thumbnail_width`, `thumbnail_height`, `max_size`, `max_files`, `mime_types`, `data_mapping`, `parallel_uploads`, `upload_multiple`, `inline` (the upload through `ux_dropzone`) |
| `ImageType` | `FileType` | `imageupload` | `thumbnail`, `modal`, `cropper` |
| `AvatarType` | `ImageType` | `avatar` | none |
| `AudioType`, `VideoType` | `FileType` | `audioupload`, `videoupload` | `multisource` |
| `CropperType` | `FormType` | `cropper` | `cropper_info`, `cropper_actions`, `cropper`, `quadrant`, `target`, `natural_width`, `natural_height`, `fields`, `aspectRatios` |
| `EditorType` | `HiddenType` | `editor` | `placeholder`, `collab_autosave`, `collab_live` (Editor.js; its uploads in `base.twig.editor.storage`) |
| `WysiwygType` | `HiddenType` | `wysiwyg` | `theme`, `placeholder`, `height`, `modules` |

> [!WARNING]
> Known defect: `EditorType` and `WysiwygType` report two more options, named `0` and `1`: their
> `configureOptions()` writes `'empty_data', null,` (a comma where `=>` is meant), so the option
> resolver declares option `0` with the value `'empty_data'` and option `1` with `null`, and
> `empty_data` keeps `HiddenType`'s default.

## Form types (`Base\Form\Type`)

The forms of the core's pages. `LayoutSettingListType` and
`LayoutWidgetListType` are services; the others are built by their class.

| Type | Block prefix | Its own options | Used by |
|---|---|---|---|
| `SecurityLoginType` | `_base_security_login` | `identifier`, `allow_login_token` | `security_login` |
| `SecurityLoginTokenType` | `_base_security_login_token` | `allow_login_token` | the sign-in by an e-mailed link |
| `Login2FAType` | `login2_fa` | none | `user_settings_2fa` (the code that confirms a new authenticator app) |
| `SecurityLoginTwoFactorType` | `security_login_two_factor` | none | no page of the core |
| `SecurityRegistrationType` | `_base_security_registration` | none | `security_register` |
| `SecurityResetPasswordType`, `SecurityResetPasswordConfirmType` | `security_reset_password`, `security_reset_password_confirm` | none | `security_resetPassword`, `security_resetPasswordWithToken` |
| `UserProfileType` | `_base_user_profile` | none | `user_profileEdit` (as `App\Form\Type\UserProfileType`, its alias, with `use_model`) |
| `UserSettingsType` | `user_settings` | none | no page of the core (`user_settings` builds its forms itself) |
| `UserSearchType` | `user_search` | none | `user_search` |
| `ThreadSearchType`, `ThreadSearchbarType` | `thread_search`, `thread_searchbar` | `parent` (the searchbar) | `thread_search` |
| `ContactType` | `_base_contact` | `phone`, `subject`, `attachments`, `buttons`, `trap`, `privacy`, `privacy_consent`, `privacy_parameters` | `base_contact` |
| `CommentType` | `comment` | `signed_in`, `recaptcha`, `max_length`, `placeholders`, `trap_class` | [Comments](../40-commons/comments.md) |
| `PrivacyType` | `base_privacy` | `notice`, `notice_parameters`, `consent`, `consent_label`, `consent_message` | the privacy notice and consent of a form |
| `LayoutSettingListType` | `_base_layout_setting_list` | `fields`, `fields[single_locale]`, `excluded_fields`, `locale` | the settings' forms of the back office |
| `LayoutWidgetListType` | `_base_layout_widget_list` | `widgets`, `sortable`, `excluded_widgets`, `locale` | the widgets' forms of the back office |
