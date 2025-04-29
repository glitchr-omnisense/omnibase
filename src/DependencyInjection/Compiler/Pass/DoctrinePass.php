<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\DatabaseSubscriber\EnumSubscriber;
use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DoctrinePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $this->processEntityMapping($container);
        $this->processRegisterRepositories($container);
        $this->processEnumSubscribers($container);
    }

    public function getStubSourceDir(): string
    {
        return dirname(__FILE__, 5).'/base-plugin/stubs';
    }

    public function getBundleSourceDir(): string
    {
        return dirname(__FILE__, 4).'/src';
    }

    public function getClassName(string $classFile): string
    {
        return basename($classFile, '.php');
    }

    public function getClassFiles(string $path): array
    {
        if (!is_dir($path)) return [];

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {

                $fqcn = $this->getNamespace($file)."\\".$this->getClassName($file);
                $classes[$fqcn] = $file;
            }
        }

        return $classes;
    }

    public function getNamespace(string $classFile): string
    {
        if (!file_exists($classFile)) {
            return '';
        }
    
        $lines = file($classFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^namespace\s+([^;]+);/', $line, $matches)) {
                return trim($matches[1]);
            }
        }
    
        return '';
    }

    public function processEnumSubscribers(ContainerBuilder $container)
    {
        if (!InstalledVersions::isInstalled('doctrine/dbal')) {
            return;
        }

        $dbalVersion = InstalledVersions::getVersion('doctrine/dbal');
        if (version_compare($dbalVersion, '4.0.0', '<')) return;

        $definition = $container->getDefinition('doctrine.dbal.default_connection.event_manager');
        $definition->addMethodCall('addEventSubscriber', [new Reference(EnumSubscriber::class)]);
    }

    public function processRegisterRepositories(ContainerBuilder $container)
    {
        if (!$container->has('doctrine.orm.default_entity_manager')) {
            return;
        }

        // Register repositories as services
        foreach ($this->getClassFiles($this->getBundleSourceDir()."/Repository") as $file) {
            
            $className = $this->getNamespace($file)."\\".$this->getClassName($file);
            if (!class_exists($className)) continue;

            $container->register($className, $className)
                      ->addTag("doctrine.repository_service")
                      ->addArgument(new Reference('doctrine'));
        }

        // Register repositories as services
        foreach ($this->getClassFiles($this->getStubSourceDir()."/Repository") as $file) {

            $className = $this->getNamespace($file)."\\".$this->getClassName($file);
            if (!class_exists($className)) continue;

            $container->register($className, $className)
                      ->addTag("doctrine.repository_service")
                      ->addArgument(new Reference('doctrine'));
        }
    }

    public function processEntityMapping(ContainerBuilder $container)
    {
        if (!$container->has('doctrine.orm.default_entity_manager')) {
            return;
        }

        // // Register repositories as services
        // foreach ($this->getClassFiles($this->getBundleSourceDir()."/Entity") as $file) {
            
        //     $className = $this->getNamespace($file)."\\".$this->getClassName($file);
        //     if (!class_exists($className)) continue;

        //     $container->register($className, $className)
        //               ->addTag("doctrine.repository_service")
        //               ->addArgument(new Reference('doctrine'));
        // }

        // // Register repositories as services
        // foreach ($this->getClassFiles($this->getStubSourceDir()."/Entity") as $file) {

        //     $className = $this->getNamespace($file)."\\".$this->getClassName($file);
        //     if (!class_exists($className)) continue;

        //     $container->register($className, $className)
        //               ->addTag("doctrine.repository_service")
        //               ->addArgument(new Reference('doctrine'));
        // }
    }
}