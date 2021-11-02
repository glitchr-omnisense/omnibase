<?php

namespace Base\Command;

use App\Entity\User;
use Base\Annotations\Annotation\Uploader;
use Base\Annotations\AnnotationInterface;
use Base\Annotations\AnnotationReader;
use Base\BaseBundle;
use Base\Entity\Thread;
use Base\Entity\User\Log;
use Base\Repository\ThreadRepository;
use Base\Repository\User\LogRepository;
use Base\Service\BaseService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\HttpFoundation\Request;

class UploaderEntitiesCommand extends Command
{
    protected static $defaultName = 'uploader:entities';

    public function __construct(EntityManager $entityManager, BaseService $baseService)
    {
        $this->entityManager = $entityManager;
        $this->baseService = $baseService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('entity',   null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific entity ? (prefix: App\\Entity)');
        $this->addOption('property', null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific entity property ?');
        $this->addOption('uuid', null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific uuid ?');

        $this->addOption('show',   false, InputOption::VALUE_NONE, 'Do you want to list entities using "Uploader" annotation ?');

        $this->addOption('orphans',        false, InputOption::VALUE_NONE, 'Do you want to get orphans ?');
        $this->addOption('show-orphans',   false, InputOption::VALUE_NONE, 'Do you want to show orphans ?');
        $this->addOption('delete-orphans', false, InputOption::VALUE_NONE, 'Do you want to delete orphans ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$output instanceof ConsoleOutputInterface)
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
    
        $output->getFormatter()->setStyle('info', new OutputFormatterStyle('green', null, ['bold']));
        $output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow', null, ['bold']));
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, ['bold']));
        $output->getFormatter()->setStyle('ln', new OutputFormatterStyle('cyan', null, ['bold']));

        $this->entity      = $input->getOption('entity');
        $this->property    = $input->getOption('property');
        $this->uuid    = $input->getOption('uuid');

        $this->showEntries  = $input->getOption('show');

        $this->orphans   = $input->getOption('orphans');
        $this->showOrphans   = $input->getOption('show-orphans');
        $this->deleteOrphans = $input->getOption('delete-orphans');

        $this->appEntities = "App\\Entity\\".$this->entity;
        $appAnnotations = $this->getUploaderAnnotations($this->appEntities);
        if(!$appAnnotations)
            $output->section()->write("<warning>Uploader annotation not found for \"$this->appEntities\"</warning>");

        $this->baseEntities = "Base\\Entity\\".$this->entity;
        $baseAnnotations = $this->getUploaderAnnotations($this->baseEntities);
        if(!$baseAnnotations)
            $output->section()->write("<warning>Uploader annotation not found for \"$this->baseEntities\"</warning>");

        $annotations = array_merge($appAnnotations, $baseAnnotations);
        foreach($annotations as $class => $_) {

            foreach($_ as $field => $annotation) {

                if($this->property && $field != $this->property) continue;

                $output->section()->write("<info>Processing entity $class..</info>");
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
        
        $this->fileList[$class."::".$field] = $this->fileList[$class."::".$field] 
        ?? array_values(array_map(
            function($f) { return "/".$f->path(); }, 
            $filesystem->listContents($classPath)->toArray()
        ));

        if($this->uuid)
            $this->fileList[$class."::".$field] = array_filter($this->fileList[$class."::".$field], fn($f) => basename($f) == $this->uuid);

        return $this->fileList[$class."::".$field];
    }

    public function getOrphanFiles(string $class, string $field, Uploader $annotation)
    {
        $fileList = $this->getFileList($class, $field, $annotation);
        $classMetadata = $this->entityManager->getClassMetadata($class);
        $fileListInDatabase = array_map(function($entity) use ($classMetadata, $field, $annotation) { 

            return $annotation->getPath($entity, $classMetadata->getFieldValue($entity, $field));

        }, $this->getEntries($class));
        
        return array_values(array_diff($fileList, $fileListInDatabase));
    }

    public function deleteOrphanFiles(Uploader $annotation, array $fileList)
    {
        $filesystem = Uploader::getFilesystem($annotation->getStorage());

        foreach($fileList as $file)
            $filesystem->delete($file);

        return true;
    }
}
