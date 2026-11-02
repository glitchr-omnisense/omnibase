<?php

use Base\Services;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The core's list of services (Base\Services::definitions(), plan "omnibase agnostic", step F5a), read by
 * Symfony's container as Laravel's and Omnibridge\Container read it: a listed service the configuration
 * above does not define is defined from the list (autowired, public, its arguments given - '@id' a service,
 * '%name%' a parameter, as Symfony writes them), and each listed contract the configuration does not alias
 * itself is aliased to its service. Loaded last (BaseExtension), so that the services
 * and aliases of config/services*.php - which still wire the listed services, until omnibridge/symfony
 * reads the list in their place (F5c) - stay what they are; tests/ServicesTest checks that both agree.
 */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();
    $reference = static function (mixed $value) use (&$reference): mixed {
        if (\is_array($value)) {
            return array_map($reference, $value);
        }

        return \is_string($value) && str_starts_with($value, '@') && !str_starts_with($value, '@@') ? service(substr($value, 1)) : (\is_string($value) && str_starts_with($value, '@@') ? substr($value, 1) : $value);
    };
    foreach (Services::definitions() as $definition) {
        $id = $definition['id'] ?? $definition['class'];
        if (!$builder->has($id)) {
            if (!class_exists($definition['class'])) {
                continue;
            }
            $arguments = [];
            foreach ($definition['arguments'] ?? [] as $name => $value) {
                $arguments[str_starts_with($name, '$') ? $name : '$'.$name] = $reference($value);
            }
            $services->set($id, $definition['class'])->autowire()->public()->args($arguments);
        }
        foreach ($definition['contracts'] ?? [] as $contract) {
            if (!$builder->has($contract)) {
                $services->alias($contract, $id);
            }
        }
    }
};
