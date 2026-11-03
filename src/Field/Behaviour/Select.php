<?php

namespace Base\Field\Behaviour;

use Omnibridge\Form\Endpoint;

/**
 * Kind::CHOICE and the kinds that choose among a list (role, state, gender,
 * country, currency, locale, icon, an association picked rather than
 * embedded): a select2 select, its entries given or searched remotely.
 *
 * The neutral part of omnibase's SelectType: its options and their
 * normalization (required unless tags are typed, a highlight only without
 * tags, the tags' own separators), select2's settings as SelectType hands
 * them to its script (select2()), the remote search's route (endpoints(),
 * `autocomplete_endpoint`, the host's ux_autocomplete). What reads records -
 * guessing the class from the data, the records chosen, an admin's edit link
 * - stays with the host's type, which passes it in as context.
 */
class Select extends AbstractBehaviour
{
    /** @param array<string, mixed> $defaults a kind's own over the select's (the role's enum, the state's) */
    public function __construct(private readonly array $defaults = [])
    {
    }

    protected function defaults(): array
    {
        return $this->defaults + [
            'class' => null,
            'error_bubbling' => false,
            'disable' => false,
            'choices' => null,
            'choice_thumbnails' => [],
            'choice_filter' => false,
            'select2' => [],
            'select2-template' => null,
            'select2-templateResult' => null,
            'select2-templateSelection' => null,
            'theme' => 'bootstrap4',
            'empty_data' => null,
            'placeholder' => '@fields.select.placeholder',
            'capitalize' => null,
            'language' => null,
            'required' => null,
            'required_when_multiple' => false,
            'multiple' => null,
            'multivalue' => false,
            'vertical' => false,
            'maximum' => 0,
            'tabulation' => '1.75em',
            'tags' => false,
            'highlight' => null,
            'minimumInputLength' => 0,
            'tokenSeparators' => [' ', ',', ';'],
            'closeOnSelect' => null,
            'selectOnClose' => false,
            'minimumResultsForSearch' => 0,
            'dropdownCssClass' => null,
            'containerCssClass' => null,
            'use_html' => false,
            'avatar' => false,
            'href' => null,
            'webpack_entry' => 'form.select2',
            'autocomplete' => null,
            'autocomplete_endpoint' => 'ux_autocomplete',
            'autocomplete_endpoint_parameters' => [],
            'autocomplete_fields' => [],
            'autocomplete_data' => null,
            'autocomplete_processResults' => null,
            'autocomplete_delay' => 500,
            'autocomplete_type' => 'POST',
            'sortable' => null,
        ];
    }

    /**
     * The options resolved; without choices, those of the class when it is
     * an enum (Choices::of()), a remote search when it is a record's.
     */
    public function options(array $given): array
    {
        $options = self::normalize(parent::options($given));
        if (null === $options['choices'] && \is_string($options['class'])) {
            $options['choices'] = Choices::of($options['class']);
            if (null === $options['choices'] && class_exists($options['class'])) {
                $options['autocomplete'] ??= true;
            }
        }
        if (null === $options['choices']) {
            $options['autocomplete'] ??= false;
        }

        return $options;
    }

    /**
     * SelectType's normalizers, on resolved options.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $options): array
    {
        $options['required'] ??= true != $options['tags'];
        $options['highlight'] = (true != $options['tags']) && $options['highlight'];
        if (\is_array($options['tags']) && $options['tags']) {
            $options['tokenSeparators'] = $options['tags'];
        }

        return $options;
    }

    /**
     * select2's settings, as SelectType hands them to its script
     * (data-select2-options): the given `select2` completed by the options.
     *
     * @param array<string, mixed>     $options  resolved
     * @param bool                     $multiple what the field is, once guessed
     * @param string|null              $search   the remote search's address; null without one
     * @param string                   $language select2's language, fr
     * @param string|null              $placeholder the placeholder in the page's words; translated here when null
     * @param list<array<string,mixed>> $entries the entries select2 starts with (data)
     * @param list<mixed>              $selected the values chosen
     *
     * @return array<string, mixed>
     */
    public static function select2(array $options, bool $multiple, ?string $search, string $language, ?string $placeholder = null, array $entries = [], array $selected = []): array
    {
        $select = (array) $options['select2'];
        $select['multiple'] = $multiple ? 'multiple' : '';
        foreach (['template', 'templateResult', 'templateSelection'] as $template) {
            if ($options['select2-'.$template]) {
                $select[$template] = $options['select2-'.$template];
            }
        }
        if (null !== $search) {
            $select['ajax'] = ['url' => $search, 'type' => $options['autocomplete_type'], 'delay' => $options['autocomplete_delay'], 'dataType' => 'json', 'html' => $options['use_html'], 'cache' => true];
            if (!\array_key_exists('autocomplete_data', $select) && null !== $options['autocomplete_data']) {
                $select['ajax']['data'] = $options['autocomplete_data'];
            }
            if (!\array_key_exists('autocomplete_processResults', $select) && null !== $options['autocomplete_processResults']) {
                $select['ajax']['processResults'] = $options['autocomplete_processResults'];
            }
        }

        if (!\array_key_exists('minimumResultsForSearch', $select)) {
            $select['minimumResultsForSearch'] = $options['minimumResultsForSearch'];
        }
        if (!\array_key_exists('closeOnSelect', $select)) {
            $select['closeOnSelect'] = $options['closeOnSelect'] ?? !$multiple;
        }
        if (!\array_key_exists('selectOnClose', $select)) {
            $select['selectOnClose'] = $options['selectOnClose'];
        }
        if (!\array_key_exists('dropdownCssClass', $select) && null !== $options['dropdownCssClass']) {
            $select['dropdownCssClass'] = $options['dropdownCssClass'];
        }
        $select['containerCssClass'] ??= '';
        $select['dropdownCssClass'] ??= '';
        if ($options['vertical']) {
            $select['containerCssClass'] .= ' select2-selection--vertical';
        }
        if ($options['tags'] && null === $search && empty($options['choices'])) {
            $select['containerCssClass'] .= ' select2-selection--wrap';
            $select['dropdownCssClass'] .= ' select2-selection--hide';
        }
        if (!\array_key_exists('placeholder', $select) && null !== $options['placeholder']) {
            $select['placeholder'] = $placeholder ?? self::trans((string) ($options['placeholder'] ?? ''), [], '@fields');
        }
        if (!\array_key_exists('multivalue', $select) && null !== $options['multivalue']) {
            $select['multivalue'] = $options['multivalue'];
        }
        if (!\array_key_exists('language', $select)) {
            $select['language'] = $language;
        }
        // Separators cut what is typed into new choices: tags only (select2's tokenizer throws without them).
        if (!\array_key_exists('tokenSeparators', $select) && $options['tags']) {
            $select['tokenSeparators'] = $options['tokenSeparators'];
        }
        if (!\array_key_exists('allowClear', $select)) {
            $select['allowClear'] = \array_key_exists('required', $options) && !$options['required'];
        }
        if (!\array_key_exists('maximum', $select)) {
            $select['maximum'] = (\array_key_exists('maximum', $options) && $options['maximum'] > 0) ? $options['maximum'] : '';
        }
        if (!\array_key_exists('tags', $select)) {
            $select['tags'] = \array_key_exists('tags', $options) && $options['tags'];
        }
        if (!\array_key_exists('theme', $select)) {
            $select['theme'] = $options['theme'];
        }
        $select['data'] = $entries;
        $select['selected'] = $selected;

        return $select;
    }

    /**
     * The vars select2's row reads (select2_widget): the settings as JSON, the
     * highlight, the tags' separators, the tabulation, the sorting. A host
     * that searches remotely passes its address in $options['search_url'].
     */
    public function vars(array $options, mixed $shown, string $id): array
    {
        $multiple = true === $options['multiple'];
        $selected = array_values(array_filter(array_map(static fn (mixed $value): ?string => \is_scalar($value) ? (string) $value : null, \is_array($shown) ? $shown : [$shown]), static fn (?string $value): bool => null !== $value && '' !== $value));
        $vars = [
            'select2' => json_encode(self::select2($options, $multiple, $options['search_url'] ?? null, self::language(), null, $options['entries'] ?? [], $selected)),
            'select2-href' => $options['href'],
            'highlight' => $options['highlight'],
            'tabulation' => $options['tabulation'],
            'select2-sortable' => $options['sortable'] && false == $options['multivalue'],
        ];
        if ($options['tags']) {
            $vars['tokenSeparators'] = $options['tokenSeparators'];
        }
        if ($multiple) {
            $vars['required'] = (bool) $options['required_when_multiple'];
        }

        return $vars;
    }

    /**
     * One of select2's entries as this kind shows it, once resolved (Autocomplete's id, icon, text, html):
     * a kind whose type says more of its choices (the countries' flags) completes it.
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function entry(array $entry, array $options): array
    {
        return $entry;
    }

    /**
     * A select2 is drawn as its form's `choice` part (name[choice], as
     * SelectType's compound form names it): the value is that part's.
     */
    public function reverse(mixed $submitted, array $options): mixed
    {
        return \is_array($submitted) && \array_key_exists('choice', $submitted) ? $submitted['choice'] : $submitted;
    }

    /** The remote search, where the field is autocompleted: the host's ux_autocomplete (or the field's own route). */
    public function endpoints(array $options): array
    {
        if (!$options['autocomplete'] || !\is_string($options['autocomplete_endpoint'])) {
            return [];
        }

        return ['search' => new Endpoint($options['autocomplete_endpoint'], (array) ($options['autocomplete_endpoint_parameters'] ?? []), (string) $options['autocomplete_type'])];
    }

    protected function controller(): ?string
    {
        return 'select2';
    }
}
