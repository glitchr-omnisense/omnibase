<?php

namespace Base\Contract;

use Base\Service\TranslatorInterface;

/**
 * What an entity is called: its noun, singular or plural, at a level of
 * politeness, for a gender - "Article", "Articles", "Commande" - and the
 * label of one of its properties, from the "entities" catalogues (plan
 * "omnibase agnostique", C4b): what an entity's __toString() falls back on
 * when it has no title, what a list or a message names it by.
 *
 * The options are Base\Service\Translator's: TranslatorInterface::NOUN_SINGULAR
 * or NOUN_PLURAL, Translator::GENDERNESS_* and POLITENESS_*, alone or in an
 * array. The key is the class's name less its entity namespace (App\Entity\,
 * Base\Entity\, a bundle's Base\Marketplace\Entity\ as "marketplace."), in
 * snake case: Base\Entity\Thread\Tag is "thread.tag", Base\Marketplace\Entity\
 * Order "marketplace.order".
 *
 * The rule is the Fondation's, Base\Service\Model\EntityNouns, over the
 * Translations contract and the entity namespaces of the application's
 * bundles; a host gives it those two. Under Symfony it is the one
 * Base\Service\Translator::transEntity() answers by, the same strings.
 */
interface Nouns
{
    /**
     * The noun of an entity (an object or its class), or of one of its
     * properties when one is named; when the catalogues have none, the key
     * asked in lower case ("thread.tag._singular"). Null for a name that is
     * no class's key at all (an empty string).
     *
     * @param string|list<string> $options
     */
    public function entity(object|string $entityOrClass, ?string $property = null, string|array $options = TranslatorInterface::NOUN_SINGULAR): ?string;

    /**
     * Whether the catalogues hold that noun, under one of the keys entity()
     * tries.
     *
     * @param string|list<string> $options
     */
    public function entityExists(object|string $entityOrClass, ?string $property = null, string|array $options = TranslatorInterface::NOUN_SINGULAR): bool;
}
