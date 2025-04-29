<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DoctrineAliasesPass implements CompilerPassInterface
{
    public function getAppPath(): string
    {
        return dirname(__FILE__, 8)."/src/Repository";
    }

    public function getClassName(string $classFile): string
    {
        return basename($classFile, '.php');
    }

    public function getClassPath(): string
    {
        return dirname(__FILE__, 4)."/Repository";
    }
    
    public function getClassFiles(): array
    {
        $locationPath = $this->getClassPath();
        if (!is_dir($locationPath)) return [];

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($locationPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {

                $fqcn = $this->getNamespace($file)."\\".$this->getClassName($file);
                $relativePath = str_replace($locationPath . DIRECTORY_SEPARATOR, '', $file);
                $classes[$fqcn] = $relativePath;                
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

    public function process(ContainerBuilder $container)
    {
        // Register repositories as services
        foreach ($this->getClassFiles() as $file) {
            
            $baseFile = $this->getClassPath()."/".$file;
            $baseName = $this->getNamespace($baseFile)."\\".$this->getClassName($baseFile);
            if (file_exists($baseFile) && class_exists($baseName)) {
                $container->register($baseName, $baseName)->addTag("doctrine.repository_service")->addArgument(new Reference('doctrine'));
            }

            $appFile = $this->getAppPath()."/".$file;
            $appName = preg_replace("/^Base\\\\/", "App\\", $this->getNamespace($baseFile))."\\".$this->getClassName($appFile);
            if (!file_exists($appFile) && class_exists($appName)) {
                $container->register($appName)->addTag("doctrine.repository_service")->addArgument(new Reference('doctrine'));
            }
        }
    }
}