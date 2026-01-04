<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\Database\Filter\TrashFilter;
use Base\Database\Filter\VaultFilter;
use Base\Database\Function\JsonArrayPosition;
use Base\Database\Function\Rand;
use DoctrineExtensions\Query\Mysql\Field;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql as DqlFunctions;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DoctrineConfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('doctrine.orm.configuration')) {
            return;
        }

        $definition = $container->getDefinition('doctrine.orm.configuration');

        // Add filters
        $definition->addMethodCall('addFilter', ['trash_filter', TrashFilter::class]);
        $definition->addMethodCall('addFilter', ['vault_filter', VaultFilter::class]);

        // Add DQL numeric functions
        $definition->addMethodCall('addCustomNumericFunction', ['RAND', Rand::class]);

        if (class_exists(Field::class)) {
            $definition->addMethodCall('addCustomNumericFunction', ['FIELD', Field::class]);
        }

        // Add DQL string functions
        if (class_exists(DqlFunctions\JsonExtract::class)) {
            $definition->addMethodCall('addCustomStringFunction', [
                DqlFunctions\JsonExtract::FUNCTION_NAME,
                DqlFunctions\JsonExtract::class
            ]);
        }

        if (class_exists(DqlFunctions\JsonSearch::class)) {
            $definition->addMethodCall('addCustomStringFunction', [
                DqlFunctions\JsonSearch::FUNCTION_NAME,
                DqlFunctions\JsonSearch::class
            ]);
        }

        if (class_exists(DqlFunctions\JsonContains::class)) {
            $definition->addMethodCall('addCustomStringFunction', [
                DqlFunctions\JsonContains::FUNCTION_NAME,
                DqlFunctions\JsonContains::class
            ]);
        }

        if (class_exists(JsonArrayPosition::class)) {
            $definition->addMethodCall('addCustomStringFunction', [
                JsonArrayPosition::FUNCTION_NAME,
                JsonArrayPosition::class
            ]);
        }

        // Optional: set default query hint (if needed)
        $definition->addMethodCall('setDefaultQueryHint', [
            \Doctrine\ORM\Query::HINT_CUSTOM_TREE_WALKERS,
            []
        ]);
    }
}