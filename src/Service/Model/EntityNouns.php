<?php

namespace Base\Service\Model;

use Base\Contract\Nouns;
use Base\Contract\Translations;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;

/**
 * The Nouns contract's rule (plan "omnibase agnostique", C4b): an entity's
 * noun read from the "entities" catalogues through the Translations contract,
 * by the key of its class and the variants its options ask for
 * (Base\Service\Model\NounVariants), the first one the catalogues hold.
 *
 * It was Base\Service\Translator::transEntity(), transEntityExists() and
 * parseClass(), which now answer by an instance of this class over the
 * translator itself, so Symfony and a host without it name an entity alike.
 * What the rule needs of a framework is given to its constructor: the
 * Translations contract, and the entity namespaces of the application's
 * bundles (namespacesOf() turns a list of bundle classes into them: under
 * Symfony, omnibase's BaseBundle::getBundles(), the Base\<Name>\<Name>Bundle
 * classes declared). App\Entity\, Base\Entity\ and Doctrine's proxies are
 * always known.
 */
class EntityNouns implements Nouns
{
    public const DOMAIN = Translator::DOMAIN_ENTITY;

    /**
     * The entity namespaces every application has, and what each becomes in
     * a key: nothing (App\Entity\Thread and Base\Entity\Thread are "thread").
     */
    public const NAMESPACES = [
        "Proxies\\__CG__\\" => "",
        "App\\Entity\\" => "",
        "Base\\Entity\\" => "",
    ];

    /** @var array<string, string> an entity namespace => its prefix in a key, in the order they are replaced */
    private array $namespaces;

    /**
     * @param array<string, string> $namespaces the bundles' entity namespaces and their prefixes (namespacesOf())
     */
    public function __construct(private readonly Translations $translations, array $namespaces = [])
    {
        $this->namespaces = self::NAMESPACES;
        foreach ($namespaces as $namespace => $prefix) {
            $this->namespaces[$namespace] ??= $prefix;
        }
    }

    /**
     * The entity namespaces of these bundles (classes or objects): a bundle
     * Base\Marketplace\MarketplaceBundle has its entities under
     * Base\Marketplace\Entity\, whose keys start with "marketplace.".
     *
     * @param iterable<object|string> $bundles
     *
     * @return array<string, string>
     */
    public static function namespacesOf(iterable $bundles): array
    {
        $namespaces = [];
        foreach ($bundles as $bundle) {
            $namespace = dirname_namespace(is_object($bundle) ? get_class($bundle) : $bundle) . "\\Entity\\";
            $base = explode("\\", $namespace)[1] ?? null;
            if ($base) {
                $namespaces[$namespace] ??= lcfirst($base) . "\\";
            }
        }

        return $namespaces;
    }

    /** @return array<string, string> the entity namespaces known, and their prefixes */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * The key of a class in the "entities" catalogues: its name less its
     * entity namespace, dotted, in snake case (Base\Entity\Thread\Tag:
     * "thread.tag"; Base\Marketplace\Entity\Order: "marketplace.order").
     */
    public function key(object|string $entityOrClass): string
    {
        $class = is_object($entityOrClass) ? get_class($entityOrClass) : $entityOrClass;
        $class = str_replace(array_keys($this->namespaces), array_values($this->namespaces), $class);

        return camel2snake(implode(".", array_unique(explode("\\", $class))));
    }

    public function entity(object|string $entityOrClass, ?string $property = null, string|array $options = TranslatorInterface::NOUN_SINGULAR): ?string
    {
        if (!is_array($options)) {
            $options = array_filter([$options]);
        }

        $key = $this->key($entityOrClass);
        if (!$key) {
            return null;
        }

        $id = $key . self::property($property);
        [$suffixes, $asked, $ownLevel] = NounVariants::of($options);

        // A level the call gave is the one: the site's is not asked on top of it.
        $parameters = $ownLevel ? [Translator::TRANSLATION_POLITENESS => "none"] : [];

        $text = null;
        foreach ($suffixes as $suffix) {
            $candidate = mb_strtolower($id . $suffix);
            if ($this->translations->exists($candidate, self::DOMAIN)) {
                $text = $this->translations->trans($candidate, $parameters, self::DOMAIN);
                break;
            }
        }

        if (!$text && empty($options)) {
            throw new \LogicException("No translation found for \"@" . self::DOMAIN . "." . $id . "\" and no permutation option provided");
        }

        return $text ? mb_ucfirst($text) : mb_strtolower($id . implode("", $asked));
    }

    public function entityExists(object|string $entityOrClass, ?string $property = null, string|array $options = TranslatorInterface::NOUN_SINGULAR): bool
    {
        if (!is_array($options)) {
            $options = [$options];
        }

        $id = $this->key($entityOrClass) . self::property($property);
        [$suffixes] = NounVariants::of($options);
        foreach ($suffixes as $suffix) {
            if ($this->translations->exists(mb_strtolower($id . $suffix), self::DOMAIN)) {
                return true;
            }
        }

        return false;
    }

    /** A property's part of the key: "._properties.published_at". */
    private static function property(?string $property): string
    {
        return $property ? "." . Translator::TRANSLATION_PROPERTIES . "." . camel2snake($property) : "";
    }
}
