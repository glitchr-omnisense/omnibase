<?php

namespace Base\Console\Command;

use Base\Annotations\Annotation\Uploader;
use Base\Annotations\AnnotationReader;

use Base\Service\BaseService;

use League\Flysystem\FileAttributes;
use Base\Console\Command;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class UploaderEntitiesCommand extends Command
{
    protected static $defaultName = 'uploader:entities';

    public function __construct(EntityManagerInterface $entityManager, BaseService $baseService)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->baseService = $baseService;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();        
    }

    protected function configure(): void
    {
        $this->addOption('entity',   null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific entity ?');
        $this->addOption('property', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific property ?');
        $this->addOption('uuid', null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific uuid ?');

        $this->addOption('show',   false, InputOption::VALUE_NONE, 'Do you want to list entities using "Uploader" annotation ?');

        $this->addOption('orphans',        false, InputOption::VALUE_NONE, 'Do you want to get orphans ?');
        $this->addOption('show-orphans',   false, InputOption::VALUE_NONE, 'Do you want to show orphans ?');
        $this->addOption('delete-orphans', false, InputOption::VALUE_NONE, 'Do you want to delete orphans ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityName    = str_strip($input->getOption('entity'), "App\\Entity\\");
        $this->property      = $input->getOption('property');
        $this->uuid          = $input->getOption('uuid');

        $this->showEntries   = $input->getOption('show');

        $this->orphans       = $input->getOption('orphans');
        $this->showOrphans   = $input->getOption('show-orphans');
        $this->deleteOrphans = $input->getOption('delete-orphans');

        $this->appEntities = "App\\Entity\\".$this->entityName;
        $appAnnotations = $this->getUploaderAnnotations($this->appEntities);
        if(!$appAnnotations)
            $output->section()->write("<warning>Uploader annotation not found for \"$this->appEntities\"</warning>");

        $this->baseEntities = "Base\\Entity\\".$this->entityName;
        $baseAnnotations = $this->getUploaderAnnotations($this->baseEntities);
        if(!$baseAnnotations)
            $output->section()->write("<warning>Uploader annotation not found for \"$this->baseEntities\"</warning>");

        $annotations = array_merge($appAnnotations, $baseAnnotations);
        foreach($annotations as $class => $_) {

            foreach($_ as $field => $annotation) {

                if($this->property && $field != $this->property) continue;

                $output->section()->write("<info>Processing entityName $class..</info>");
                if(count($annotation) > 1)
                    throw new \LogicException("Unexpected \"Uploader\" annotation found twice in $class..");

                $annotation = $annotation[0];

                $fileList = $this->getFileList($class, $field, $annotation);
                if($this->uuid) $output->section()->writeln("- Looking for files in $class::$field <ln>UUID \"$this->uuid\" found.</ln>");
                else $output->section()->writeln("- Looking for files in $class::$field <ln>".count($fileList)." file(s) found.</ln>");
                
                if($this->showEntries) {

                    foreach($fileList as $file)
                        $output->section()->writeln("  <ln>* $file</ln>");
                }

                if($this->orphans || $this->showOrphans || $this->deleteOrphans) {

                    $orphanFiles = $this->getOrphanFiles($class, $field, $annotation);
                    $nFiles = count($orphanFiles);

                    $output->section()->writeln("- Looking for orphan files in $class::$field <warning>$nFiles orphan file(s) found.</warning>");            
                    if($this->showOrphans) {
                        foreach($orphanFiles as $file)
                            $output->section()->writeln("  <warning>* $file</warning>");
                    }

                    if ($this->deleteOrphans) {

                        $this->deleteOrphanFiles($annotation, $orphanFiles);   

                        if($orphanFiles) $output->section()->writeln("  <red>* Orphan files deleted..</red>"); 
                        else  $output->section()->writeln("  <warning>* No orphan files to be deleted..</warning>"); 
                    }
                }
            }
        }
        return Command::SUCCESS;
    }

    protected function getUploaderAnnotations(?string $namespace)
    {
        $classes = array_filter(get_declared_classes(), function($c) use ($namespace) {
            return str_starts_with($c, $namespace);
        });

        $metadataClasses = [];
        foreach($classes as $class) 
            $metadataClasses[$class] = $this->entityManager->getClassMetadata($class);

        $annotations = [];
        $annotationReader = AnnotationReader::getInstance();
        foreach($metadataClasses as $class => $classMetadata) {

            $this->propertyAnnotations = $annotationReader->getPropertyAnnotations($classMetadata, Uploader::class);
            if($this->propertyAnnotations)
                $annotations[$class] = $this->propertyAnnotations;
        }

        return $annotations;
    }
    
    private $allEntries = [];
    public function getEntries($class)
    {
        $repository    = $this->entityManager->getRepository($class);
        $this->allEntries[$class] = $this->allEntries[$class] ?? $repository->findAll();
        return $this->allEntries[$class];
    }

    private $fileList = [];
    protected function getFileList(string $class, string $field, Uploader $annotation)
    {
        $classPath  = $annotation->getPath($class, "");
        $filesystem = Uploader::getFilesystem($annotation->getStorage());

        $property = $class."::".$field;
        if(!array_key_exists($property, $this->fileList))
            $this->fileList[$property] = array_values(array_filter(
                array_map(fn($f) => ($f instanceof FileAttributes) ? "/".$f->path() : null, 
                $filesystem->listContents($classPath)->toArray()
            )));

        if($this->uuid)
            $this->fileList[$property] = array_filter($this->fileList[$property], fn($f) => basename($f) == $this->uuid);

        return $this->fileList[$property];
    }

    public function getOrphanFiles(string $class, string $field, Uploader $annotation)
    {
        $fileList = $this->getFileList($class, $field, $annotation);

        $fileListInDatabase = array_map(function($entity) use ($field, $annotation) { 

            $uuid = $this->propertyAccessor->getValue($entity, $field);
            if(is_array($uuid)) return array_map(fn($uuid) => $annotation->getPath($entity, $uuid), $uuid);
            else return $annotation->getPath($entity, $uuid);

        }, $this->getEntries($class));

        return array_values(array_diff($fileList, array_flatten(".", $fileListInDatabase)));
    }

    public function deleteOrphanFiles(Uploader $annotation, array $fileList)
    {
        $filesystem = Uploader::getFilesystem($annotation->getStorage());
        foreach($fileList as $file)
            $filesystem->delete($file);

        return true;
    }
}
