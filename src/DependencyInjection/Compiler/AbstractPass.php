<?php

namespace Base\DependencyInjection\Compiler;

use Base\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Registry;

abstract class AbstractPass implements CompilerPassInterface
{
    abstract public function classFqcn(): string;
    abstract public function taggedServiceIds(): string;
    abstract public function addMethod(): string;
    public function addArguments(string $className): array
    {
        return [];
    }

    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has($this->classFqcn())) {
            return;
        }

        $definition     = $container->findDefinition($this->classFqcn());
        $taggedServices = $container->findTaggedServiceIds($this->taggedServiceIds());
        foreach ($taggedServices as $className => $tags) {
            $reference = new Reference($className);
            $args = $this->addArguments($className);
            array_prepend($args, $reference);

            $definition->addMethodCall($this->addMethod(), $args);
        }
    }
}
