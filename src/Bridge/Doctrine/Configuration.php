<?php

namespace Base\Bridge\Doctrine;

use Base\Database\Filter\TrashFilter;
use Base\Database\Filter\VaultFilter;
use Base\Database\Function\JsonArrayPosition;
use Base\Database\Function\Rand;
use Doctrine\ORM\Configuration as OrmConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use DoctrineExtensions\Query\Mysql\Field;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql as DqlFunctions;

/**
 * What omnibase adds to an entity manager's configuration (plan "omnibase
 * agnostique", C6): the `trash_filter` (soft-deleted rows hidden) and the
 * `vault_filter` (rows of another environment hidden), the DQL functions
 * (RAND, FIELD, JSON_EXTRACT, JSON_SEARCH, JSON_CONTAINS, JSON_ARRAY_POSITION,
 * those whose library is installed), the empty list of tree walkers as the
 * default hint. DoctrineConfigurationPass hands the same lists to
 * DoctrineBundle's configuration service; apply() sets them on a
 * configuration built by hand.
 */
final class Configuration
{
    public const TRASH_FILTER = 'trash_filter';
    public const VAULT_FILTER = 'vault_filter';

    /** @return array<string, class-string> */
    public static function filters(): array
    {
        return [self::TRASH_FILTER => TrashFilter::class, self::VAULT_FILTER => VaultFilter::class];
    }

    /** @return array<string, class-string> */
    public static function numericFunctions(): array
    {
        $functions = ['RAND' => Rand::class];
        if (class_exists(Field::class)) {
            $functions['FIELD'] = Field::class;
        }

        return $functions;
    }

    /** @return array<string, class-string> */
    public static function stringFunctions(): array
    {
        $functions = [];
        foreach ([DqlFunctions\JsonExtract::class, DqlFunctions\JsonSearch::class, DqlFunctions\JsonContains::class] as $function) {
            if (class_exists($function)) {
                $functions[$function::FUNCTION_NAME] = $function;
            }
        }
        $functions[JsonArrayPosition::FUNCTION_NAME] = JsonArrayPosition::class;

        return $functions;
    }

    /** @return array<string, mixed> */
    public static function defaultQueryHints(): array
    {
        return [Query::HINT_CUSTOM_TREE_WALKERS => []];
    }

    public static function apply(OrmConfiguration $configuration): void
    {
        foreach (self::filters() as $name => $className) {
            $configuration->addFilter($name, $className);
        }
        foreach (self::numericFunctions() as $name => $className) {
            $configuration->addCustomNumericFunction($name, $className);
        }
        foreach (self::stringFunctions() as $name => $className) {
            $configuration->addCustomStringFunction($name, $className);
        }
        foreach (self::defaultQueryHints() as $name => $value) {
            $configuration->setDefaultQueryHint($name, $value);
        }
    }

    /** The two filters on, the vault's for the environment given: what BaseBundle::boot() does. */
    public static function enableFilters(EntityManagerInterface $entityManager, string $environment): void
    {
        $entityManager->getFilters()->enable(self::TRASH_FILTER);
        $entityManager->getFilters()->enable(self::VAULT_FILTER)->setEnvironment($environment);
    }
}
