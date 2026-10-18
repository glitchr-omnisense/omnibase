<?php

namespace Base\Service\Model;

use Base\Service\Translator;
use Base\Service\TranslatorInterface;

/**
 * The keys tried for a noun - an entity's (Base\Service\Model\EntityNouns), an
 * enumeration's value (Base\Service\Translator::transEnum()) - after its base
 * key, for the options a call gives: a level of politeness
 * (Translator::POLITENESS_*), a gender (Translator::GENDERNESS_*), singular or
 * plural (TranslatorInterface::NOUN_*).
 *
 * Moved out of Translator::permutations() (plan "omnibase agnostique", C4b),
 * which now answers by it, so that the entities' nouns are tried in the same
 * order where there is no framework. Plain PHP: the constants are
 * Translator's, a class that loads nothing of the framework.
 */
final class NounVariants
{
    /**
     * The suffixes to try after a key for these options, the most precise
     * first: every ordering of the level of politeness, the gender and the
     * number asked; then, for a level of politeness the call gave, the levels
     * it falls back on (formal -> polite) and the same key without any; then
     * the bare key.
     *
     * @param list<string> $options
     *
     * @return array{0: list<string>, 1: list<string>, 2: bool} the suffixes, the parts asked, whether the call gave a level of its own
     */
    public static function of(array $options): array
    {
        $politeness = null;
        if (in_array(Translator::POLITENESS_PLAIN, $options)) {
            $politeness = Translator::POLITENESS_PLAIN;
        } elseif (in_array(Translator::POLITENESS_POLITE, $options)) {
            $politeness = Translator::POLITENESS_POLITE;
        } elseif (in_array(Translator::POLITENESS_FORMAL, $options)) {
            $politeness = Translator::POLITENESS_FORMAL;
        }

        $genderness = null;
        if (in_array(Translator::GENDERNESS_INCLUSIVE, $options)) {
            $genderness = Translator::GENDERNESS_INCLUSIVE;
        } elseif (in_array(Translator::GENDERNESS_MASCULINE, $options)) {
            $genderness = Translator::GENDERNESS_MASCULINE;
        } elseif (in_array(Translator::GENDERNESS_FEMININE, $options)) {
            $genderness = Translator::GENDERNESS_FEMININE;
        } elseif (in_array(Translator::GENDERNESS_NEUTRAL, $options)) {
            $genderness = Translator::GENDERNESS_NEUTRAL;
        }
        $genderness = $genderness ? "." . $genderness : "";

        $noun = null;
        if (in_array(TranslatorInterface::NOUN_PLURAL, $options)) {
            $noun = TranslatorInterface::NOUN_PLURAL;
        } elseif (in_array(TranslatorInterface::NOUN_SINGULAR, $options)) {
            $noun = TranslatorInterface::NOUN_SINGULAR;
        }
        $noun = $noun ? "." . $noun : "";

        $others = array_filter([$genderness, $noun]);
        $permutations = [];
        foreach (Translator::politenessChain($politeness) as $level) {
            foreach (get_permutations(array_merge(["." . $level], $others)) as $permutation) {
                $permutations[] = implode("", $permutation);
            }
        }
        foreach (get_permutations($others) as $permutation) {
            $permutations[] = implode("", $permutation);
        }
        $permutations[] = "";

        return [array_values(array_unique($permutations)), array_filter([$politeness ? "." . $politeness : "", $genderness, $noun]), $politeness !== null];
    }
}
