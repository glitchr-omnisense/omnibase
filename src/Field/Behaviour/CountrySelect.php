<?php

namespace Base\Field\Behaviour;

/** Kind::COUNTRY: a select2 of the countries (Base\Field\Choice\Countries), with their flags (use_html). */
final class CountrySelect extends Select
{
    /** Its name and its flag, as CountryType gives them (SelectInterface::getText(), getHtml()). */
    public function entry(array $entry, array $options): array
    {
        $code = (string) $entry['id'];
        $name = \Base\Field\Choice\Countries::getName($code);
        $entry['text'] = $name;
        $entry['html'] = "<img class='country-flag' src='/bundles/base/images/flags/".$code.".svg' alt='".$code."'> ".$name.'</>';

        return $entry;
    }

    protected function defaults(): array
    {
        return ['choices' => Choices::countries(), 'alpha3' => false, 'use_html' => true] + parent::defaults();
    }
}
