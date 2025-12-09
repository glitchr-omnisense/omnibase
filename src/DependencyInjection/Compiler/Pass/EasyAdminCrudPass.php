<?php

namespace Base\DependencyInjection\Compiler\Pass;

use EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\EasyAdminExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

class EasyAdminCrudPass implements CompilerPassInterface
{
    public static function getPriority(): int
    {
        return 1;
    }

    public function process(ContainerBuilder $container): void
    {
        // Define the directory to scan for CRUD controllers
        $directory = dirname(__FILE__, 4) . '/Controller/Admin/Crud';

        // Use Symfony Finder to locate PHP files in the directory
        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php');

        foreach ($finder as $file) {
            
            $relativePath = $file->getRelativePathname();
            $className = str_replace('/', '\\', substr($relativePath, 0, -4));

            $className = 'Base\\Controller\\Admin\\Crud\\' . $className;
            if (class_exists($className) && !\is_abstract($className)) {

                $definition = $container->register($className, $className);
                $definition->addTag(EasyAdminExtension::TAG_CRUD_CONTROLLER);
                $definition->addTag('controller.service_arguments');                
                $definition->addTag('container.service_subscriber');
                $definition->setAutowired(true);
                $definition->setAutoconfigured(true);
                $definition->setPublic(true);
            }
        }
    }
}