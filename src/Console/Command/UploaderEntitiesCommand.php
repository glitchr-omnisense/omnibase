<?php

namespace Base\Console\Command;

use Base\Annotations\Annotation\Uploader;
use Base\Annotations\AnnotationReader;
use Base\Cache\UploadWarmer;
use Base\Service\BaseService;

use League\Flysystem\FileAttributes;
use Base\Console\Command;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'uploader:entities', aliases:[], description:'')]
class UploaderEntitiesCommand extends Command
{
    public function __construct(
        BaseService $baseService, LocaleProviderInterface $localeProvider, TranslatorInterface $translator, EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag,
        UploadWarmer $uploadWarmer)
    {
        parent::__construct($baseService, $localeProvider, $translator, $entityManager, $parameterBag);

        $this->uploadWarmer = $uploadWarmer;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    protected function configure(): void
    {
        $this->addOption('entity',   null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific entity ?');
        $this->addOption('property', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific property ?');
        $this->addOption('uuid', null, InputOption::VALUE_OPTIONAL, 'Should I consider a specific uuid ?');

        $this->addOption('warmup', false, InputOption::VALUE_NONE, 'Do you want to all formats based on "Uploader" annotation ?');

        $this->addOption('orphans',        false, InputOption::VALUE_NONE, 'Do you want to get orphans ?');
        $this->addOption('delete-orphans', false, InputOption::VALUE_NONE, 'Do you want to delete orphans ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityName    ??= str_strip($input->getOption('entity'), "App\\Entity\\");
        $this->property      ??= $input->getOption('property');
        $this->uuid          ??= $input->getOption('uuid');
        $this->verbose       ??= $input->getOption('verbose');
        $this->warmup        ??= $input->getOption('warmup');
        $this->orphans       ??= $input->getOption('orphans');
        $this->deleteOrphans ??= $input->getOption('delete-orphans');

        $output->section()->writeln("\n <info>Looking for \"".Uploader::class."\"</info> annotations...");

        $nTotalFiles   = 0;
        $nTotalOrphans = 0;
        $nTotalFields  = 0;

        $this->appEntities ??= "App\\Entity\\".$this->entityName;
        $appAnnotations = $this->getUploaderAnnotations($this->appEntities);
        if(!$appAnnotations)
            $output->section()->write("\t<warning>Uploader annotation not found for \"$this->appEntities\"</warning>");

        $this->baseEntities ??= "Base\\Entity\\".$this->entityName;
        $baseAnnotations = $this->getUploaderAnnotations($this->baseEntities);
        if(!$baseAnnotations)
            $output->section()->write("\t<warning>Uploader annotation not found for \"$this->baseEntities\"</warning>");

        if($this->verbose)
            $output->section()->writeln("");

        $annotations = array_merge($appAnnotations, $baseAnnotations);
        foreach($annotations as $class => $_) {

            if(!str_starts_with($class, "Base\\Entity\\".$this->entityName) && !str_starts_with($class, "App\\Entity\\".$this->entityName))
                continue;

            $noPropertyFound = true;
            foreach($_ as $field => $annotation) {

                if($this->property && $field != $this->property) continue;
                $nTotalFields++;

                $annotation = last($annotation);
                if($annotation->getMissable()) {
                    if($this->verbose) $output->section()->write("\t           $class::$field <warning> is missable.. cannot have orphan..</warning>");
                    continue;
                }

                if($annotation->getDeclaringEntity($class, $field) != $class)
                    continue;

                $publicPath = $annotation->getFilesystem()->getPublic("", $annotation->getStorage());
                $fileList = $this->getFileList($class, $field, $annotation);
                $nTotalFiles += count($fileList);
                $noPropertyFound = false;

                if($this->verbose) {
                    if($this->uuid) $output->section()->writeln("\t           $class::$field <ln>UUID \"$this->uuid\" found.</ln>");
                    else $output->section()->writeln("\t           $class::$field <ln>".count($fileList)." file(s) found.</ln>");
                }

                if($this->verbose) {

                    foreach($fileList as $file)
                        $output->section()->writeln("\t           <ln>* ./".str_lstrip($file,$publicPath)."</ln>");
                }

                if($this->orphans || $this->deleteOrphans) {

                    $orphanFiles = $this->getOrphanFiles($class, $field, $annotation);
                    $nOrphans = count($orphanFiles);
                    $nTotalOrphans += $nOrphans;

                    $output->section()->writeln("\t           <info>Looking for orphan files</info> in $publicPath <warning>$nOrphans orphan file(s) found.</warning>");
                    if($this->verbose) {
                        foreach($orphanFiles as $file)
                            $output->section()->writeln("\t           <warning>* ./".str_lstrip($file,$publicPath)."</warning>");
                    }

                    if ($this->deleteOrphans) {

                        $this->deleteOrphanFiles($annotation, $orphanFiles);

                        if($orphanFiles) $output->section()->writeln("\t           <red>* Orphan files deleted..</red>");
                        else  $output->section()->writeln("\t           <warning>* No orphan files to be deleted..</warning>");
                    }
                }
            }

            if($noPropertyFound) {
                if($this->verbose) $output->section()->write("\t           $class::$field <warning>not declared in this class..</warning>");
                $nTotalFields--;
            }
        }

        $msg = ' [OK] '.$nTotalFields.' fields found: '.$nTotalFiles.' file(s); '.$nTotalOrphans.' orphan(s) !';
        $output->writeln('');
        $output->writeln('<info,bkg>'.str_blankspace(strlen($msg)));
        $output->writeln($msg);
        $output->writeln(str_blankspace(strlen($msg)).'</info,bkg>');
        $output->writeln('');

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
        $this->allEntries[$class] ??= $this->allEntries[$class] ?? $repository->findAll($class);

        return $this->allEntries[$class];
    }

    private $fileList = [];
    protected function getFileList(string $class, string $field, Uploader $annotation)
    {
        $classPath  = dirname($annotation->getPath($class, $field));
        $filesystem = Uploader::getFilesystem($annotation->getStorage());

        $propertyFqcn = $class."::".$field;
        if(!array_key_exists($propertyFqcn, $this->fileList)) {

            $this->fileList[$propertyFqcn] = array_values(array_filter(array_map(function($f) use ($annotation) {

                if(!$f instanceof FileAttributes) return null;
                return $annotation->getFilesystem()->getPublic($f->path(), $annotation->getStorage());

            }, $filesystem->getOperator()->listContents($classPath)->toArray())));
        }

        if($this->uuid)
            $this->fileList[$propertyFqcn] = array_filter($this->fileList[$propertyFqcn], fn($f) => basename($f) == $this->uuid);

        return $this->fileList[$propertyFqcn];
    }

    public function getOrphanFiles(string $class, string $field, Uploader $annotation)
    {
        if($annotation->getMissable()) return [];

        $fileList = $this->getFileList($class, $field, $annotation);
        $fileListInDatabase = array_map(
            fn($e) => $this->propertyAccessor->getValue($e, $field),
            $this->getEntries($class)
        );

        $injection = array_values(array_diff($fileList,array_flatten(".", $fileListInDatabase)));
        $surjection = array_values(array_diff(array_flatten(".", $fileListInDatabase), $fileList));


        return array_filter(array_unique(array_merge($injection, $surjection)));
    }

    public function deleteOrphanFiles(Uploader $annotation, array $fileList)
    {
        $publicPath = $annotation->getFilesystem()->getPublic("", $annotation->getStorage());
        $filesystem = Uploader::getFilesystem($annotation->getStorage());
        foreach ($fileList as $file)
            $filesystem->delete(str_lstrip($file, $publicPath));

        return true;
    }
}
