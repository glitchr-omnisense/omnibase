<?php

namespace Tests\Base\Contract;

use Omnibridge\Symfony\TranslatorTranslations;
use Base\Contract\Nouns;
use Base\Contract\Runtime;
use Base\Contract\Translations;
use Base\Entity\Layout\Widget;
use Base\Entity\Thread;
use Base\Service\Model\EntityNouns;
use Base\Service\Translator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What an entity is called, moved off BaseTrait's translator onto the Nouns
 * contract (plan "omnibase agnostique", C4b), under Symfony: every entity of
 * the application, in the singular, the plural, with a gender and a level of
 * politeness, and its properties' labels, are the strings the translator gave
 * before - its old rule copied here as it was (parseClass(), permutations(),
 * transPerms(), transPermExists() over transQuiet()) - through
 * Translator::transEntity() and through Runtime::nouns(); Thread's and
 * Widget's __toString() fall back on that noun as they did.
 * tests/BareTest's testTheBaseTraitSeamsAnswerWithoutTheFramework runs the
 * same rule where there is no framework.
 */
final class NounsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheRegistryHoldsTheTranslatorsNounsAndTranslations(): void
    {
        $container = static::getContainer();
        $translator = $container->get('Base\Service\Translator');

        self::assertInstanceOf(TranslatorTranslations::class, Runtime::get(Translations::class));
        self::assertInstanceOf(EntityNouns::class, Runtime::nouns());
        self::assertSame($translator->getEntityNouns(), Runtime::nouns(), 'one rule: the translator\'s own');
        self::assertSame(Runtime::nouns(), Runtime::get(Nouns::class));
        self::assertInstanceOf(Translations::class, $translator, 'the translator is the contract too');

        foreach (['base.years', '@entities.thread._singular', 'thread._plural', 'no.such.key.c4b', 'Published at'] as $id) {
            foreach ([null, 'entities', 'messages'] as $domain) {
                self::assertSame($translator->transExists($id, $domain), Runtime::get(Translations::class)->exists($id, $domain), $id.' in '.($domain ?? 'the default domain'));
                self::assertSame($translator->transExists($id, $domain, 'en'), Runtime::get(Translations::class)->exists($id, $domain, 'en'), $id.', en');
            }
        }
    }

    public function testEveryEntityIsCalledWhatTheTranslatorCalledIt(): void
    {
        $translator = static::getContainer()->get('Base\Service\Translator');
        $classes = array_map(fn ($metadata) => $metadata->getName(), static::getContainer()->get('doctrine.orm.entity_manager')->getMetadataFactory()->getAllMetadata());
        $classes = array_merge($classes, ['App\\Entity\\Ghost', 'Base\\Marketplace\\Entity\\Order', 'Proxies\\__CG__\\Base\\Entity\\Thread']);
        self::assertGreaterThan(50, count($classes));

        $options = [
            Translator::NOUN_SINGULAR,
            Translator::NOUN_PLURAL,
            [Translator::NOUN_PLURAL, Translator::GENDERNESS_FEMININE],
            [Translator::NOUN_SINGULAR, Translator::POLITENESS_POLITE],
            [Translator::POLITENESS_FORMAL, Translator::GENDERNESS_MASCULINE, Translator::NOUN_PLURAL],
        ];

        $named = 0;
        foreach (['fr', 'en'] as $locale) {
            $translator->setLocale($locale);
            foreach ($classes as $class) {
                self::assertSame(self::keyBefore($class), $translator->parseClass($class), $class);
                self::assertSame(self::keyBefore($class), Runtime::nouns()->key($class), $class);

                foreach ($options as $option) {
                    foreach ([null, 'title', 'publishedAt'] as $property) {
                        $label = $class.($property ? '::'.$property : '').', '.json_encode($option).', '.$locale;
                        $before = self::nounBefore($translator, $class, $property, $option);
                        self::assertSame($before, $translator->transEntity($class, $property, $option), $label);
                        self::assertSame($before, Runtime::nouns()->entity($class, $property, $option), $label.', the contract');
                        self::assertSame(self::existsBefore($translator, $class, $property, $option), Runtime::nouns()->entityExists($class, $property, $option), $label.', exists');
                        self::assertSame(self::existsBefore($translator, $class, $property, $option), $translator->transEntityExists($class, $property, $option), $label.', exists, the translator');
                        $named += (int) ($before !== mb_strtolower($before));
                    }
                }
            }
        }
        self::assertGreaterThan(100, $named, 'nouns found in the catalogues, not only keys');

        // No option and no noun: said aloud, as before.
        $this->expectException(\LogicException::class);
        Runtime::nouns()->entity('App\\Entity\\Ghost', 'missing', []);
    }

    public function testThreadsAndWidgetsWithoutATitleAreCalledByTheirNoun(): void
    {
        $translator = static::getContainer()->get('Base\Service\Translator');
        foreach (['fr', 'en'] as $locale) {
            $translator->setLocale($locale);

            $thread = new Thread();
            self::assertSame($translator->transEntity(Thread::class), (string) $thread, $locale);
            self::assertSame(self::nounBefore($translator, Thread::class, null, Translator::NOUN_SINGULAR), (string) $thread, $locale);
            $thread->setSlug('abbey-road');
            self::assertSame('abbey-road', (string) $thread);

            $widget = new Widget();
            self::assertSame(self::nounBefore($translator, Widget::class, null, Translator::NOUN_SINGULAR).' #', (string) $widget, $locale);
        }
        $translator->setLocale('fr');
        self::assertSame('Fil', (string) new Thread(), 'the catalogue\'s noun, not a key');
    }

    /** Translator::parseClass($class) before C4b (PARSE_NAMESPACE), as it was. */
    private static function keyBefore(string $class): string
    {
        $bundleEntityNamespaces = array_map(fn ($b) => dirname_namespace($b).'\\Entity\\', \Base\BaseBundle::getInstance()->getBundles());
        $entityNamespaces = array_merge(['Proxies\\__CG__\\', 'App\\Entity\\', 'Base\\Entity\\'], $bundleEntityNamespaces);

        $entityPrefix = array_fill(0, 3, '');
        foreach ($bundleEntityNamespaces as $namespace) {
            $baseNamespace = explode('\\', $namespace)[1] ?? null;
            if ($baseNamespace) {
                $entityPrefix[] = lcfirst($baseNamespace).'\\';
            }
        }

        $class = str_replace($entityNamespaces, $entityPrefix, $class);

        return camel2snake(implode('.', array_unique(explode('\\', $class))));
    }

    /** Translator::permutations() before C4b, as it was. */
    private static function permutationsBefore(array $options): array
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
        $genderness = $genderness ? '.'.$genderness : '';

        $noun = null;
        if (in_array(Translator::NOUN_PLURAL, $options)) {
            $noun = Translator::NOUN_PLURAL;
        } elseif (in_array(Translator::NOUN_SINGULAR, $options)) {
            $noun = Translator::NOUN_SINGULAR;
        }
        $noun = $noun ? '.'.$noun : '';

        $others = array_filter([$genderness, $noun]);
        $permutations = [];
        foreach (Translator::politenessChain($politeness) as $level) {
            foreach (get_permutations(array_merge(['.'.$level], $others)) as $permutation) {
                $permutations[] = implode('', $permutation);
            }
        }
        foreach (get_permutations($others) as $permutation) {
            $permutations[] = implode('', $permutation);
        }
        $permutations[] = '';

        return [array_values(array_unique($permutations)), array_filter([$politeness ? '.'.$politeness : '', $genderness, $noun]), null !== $politeness];
    }

    /** Translator::transEntity() before C4b (transPerms() over transQuiet()), as it was. */
    private static function nounBefore(Translator $translator, string $class, ?string $property, string|array $options): ?string
    {
        if (!is_array($options)) {
            $options = array_filter([$options]);
        }
        $id = self::keyBefore($class);
        $property = $property ? '.'.Translator::TRANSLATION_PROPERTIES.'.'.camel2snake($property) : '';
        if (!$id) {
            return null;
        }
        $id .= $property;

        [$permutations, $in, $ownLevel] = self::permutationsBefore($options);
        $parameters = $ownLevel ? [Translator::TRANSLATION_POLITENESS => 'none'] : [];
        $trans = null;
        foreach ($permutations as $permutation) {
            $trans = $translator->transQuiet(mb_strtolower($id.$permutation), $parameters, Translator::DOMAIN_ENTITY);
            if (null !== $trans) {
                break;
            }
        }

        return $trans ? mb_ucfirst($trans) : mb_strtolower($id.implode('', $in));
    }

    /** Translator::transEntityExists() before C4b, as it was. */
    private static function existsBefore(Translator $translator, string $class, ?string $property, string|array $options): bool
    {
        if (!is_array($options)) {
            $options = [$options];
        }
        $id = self::keyBefore($class).($property ? '.'.Translator::TRANSLATION_PROPERTIES.'.'.camel2snake($property) : '');
        [$permutations] = self::permutationsBefore($options);
        foreach ($permutations as $permutation) {
            if (null !== $translator->transQuiet(mb_strtolower($id.$permutation), [], Translator::DOMAIN_ENTITY)) {
                return true;
            }
        }

        return false;
    }
}
