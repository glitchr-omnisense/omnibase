<?php

namespace Base\Console\Command;

use Base\Annotations\Annotation\Uploader;
use Base\Annotations\AnnotationReader;
use Base\Console\Command;
use Base\Entity\Layout\Image;
use Base\Entity\Layout\ImageCrop;
use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Base\Imagine\Filter\Format\WebpFilter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'uploader:images:crop', aliases:[], description:'')]
class UploaderImagesCropCommand extends UploaderImagesCommand
{
    protected $input;
    protected $output;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityName  ??= str_strip($input->getOption('entity') ?? Image::class, ["App\\Entity\\", "Base\\Entity\\"]);
        $this->appEntities ??= "App\\Entity\\".$this->entityName;
        if(!is_instanceof($this->appEntities, Image::class)) {

            $this->appEntities = null;

            $msg = ' [ERR] Entity must inherit from "'.Image::class.'"';
            $output->writeln('');
            $output->writeln('<warning,bkg>'.str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)).'</warning,bkg>');
            $output->writeln('');

            return Command::FAILURE;
        }

        $this->baseEntities ??= "Base\\Entity\\".$this->entityName;
        if(!is_instanceof($this->baseEntities, Image::class))
            $this->baseEntities = null;

        $this->imageRepository = $this->entityManager->getRepository(Image::class);
        $this->imageCropRepository = $this->entityManager->getRepository(ImageCrop::class);

        return parent::execute($input, $output);
    }

    public function postProcess(mixed $class, string $field, Uploader $annotation, array $fileList)
    {
        if($field != "source") return parent::postProcess($class,$field,$annotation,$fileList);
        if($this->warmup) {

            $repository = $this->entityManager->getRepository($class);
            $images = $repository->findAll();
            $N = count($images);

            for($i = 0; $i < $N; $i++) {

                $image = $images[$i];
                $imageCrops = $image->getCrops();

                if($this->ibatch >= $this->batch && $this->batch > 0) {

                    $msg = ' [WARNING] Batch limit reached out - Program stopped for cache memory reason. Set the `--batch` limit higher, if you wish. ';
                    $this->output->writeln('');
                    $this->output->writeln('<warning,bkg>'.str_blankspace(strlen($msg)));
                    $this->output->writeln($msg);
                    $this->output->writeln(str_blankspace(strlen($msg)).'</warning,bkg>');
                    $this->output->writeln('');

                    return Command::FAILURE;
                }

                $publicDir  = $annotation->getFilesystem()->getPublic("", $annotation->storage());

                $file = $image->getSource();
                if($file === null) {

                    $this->output->section()->writeln("\t           <warning>* Image #".$image->getId()." missing.</warning>", OutputInterface::VERBOSITY_VERBOSE);
                    continue;
                }

                $hashidWebp = basename(dirname($this->imageService->imagine($file, [], ["webp" => true])));
                $hashid     = basename(dirname($this->imageService->imagine($file, [], ["webp" => false])));

                $extensions = $this->imageService->getExtensions($file);
                $extension  = first($extensions);

                if($this->isCached($file)) {

                    $this->output->section()->writeln("             <warning>* Already cached main image \"".str_lstrip($file,$publicDir)."\" .. (".($i+1)."/".$N.")</warning>", OutputInterface::VERBOSITY_VERBOSE);

                } else {

                    $this->output->section()->writeln("             <ln>* Warming up main image \"".str_lstrip($file,$publicDir)."\" .. (".($i+1)."/".$N.")</ln>", OutputInterface::VERBOSITY_VERBOSE);

                    $this->fileController->ImageWebp($hashidWebp);
                    $this->fileController->Image($hashid, $extension);

                    $this->ibatch++;
                }

                foreach($imageCrops as $imageCrop) {

                    $identifier = $imageCrop->getSlug() ?? $imageCrop->getWidth().":".$imageCrop->getHeight();
                    if($this->isCached($file, $imageCrop)) {

                        $this->output->section()->writeln("             <warning>  Already cached \"".str_lstrip($file,$publicDir)."\" (".$identifier.") .. (".($i+1)."/".$N.")</warning>", OutputInterface::VERBOSITY_VERBOSE);

                    } else {

                        $this->output->section()->writeln("             <ln>  Warming up \"".str_lstrip($file,$publicDir)."\" (".$identifier.") .. (".($i+1)."/".$N.")</ln>", OutputInterface::VERBOSITY_VERBOSE);
                        $this->ibatch++;

                        $this->fileController->ImageCrop($hashidWebp, $identifier, $extension);
                        $this->fileController->ImageCrop($hashid, $identifier, $extension);
                    }

                    $this->output->section()->writeln("                - Memory usage: ".round(memory_get_usage()/1024/1024)."MB; File: ".implode(", ", $annotation->mimeTypes())." (incl. WEBP); ".$identifier, OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
            }
        }

        return Command::SUCCESS;
    }

    public function isCached($hashid, $imageCrop = null)
    {
        if($imageCrop == null) return parent::isCached($hashid);

        //
        // Extract parameters
        $args = $this->imageService->resolve($hashid);
        if(!$args) return false;

        $filters = $args["filters"];
        $options = $args["options"];
        $path    = $args["path"];

        // Dimension information
        $imagesize = getimagesize($path);
        $naturalWidth = $imagesize[0] ?? 0;
        if($naturalWidth == 0) return true;
        $naturalHeight = $imagesize[1] ?? 0;
        if($naturalHeight == 0) return true;

        //
        // Apply filter
        // NB: Only applying cropping if ImageCrop is found ..
        //     .. otherwise some naughty users might be generating infinite amount of image
        if($imageCrop) {

            array_prepend($filters, new CropFilter(
                $imageCrop->getX0(), $imageCrop->getY0(),
                $imageCrop->getWidth0(), $imageCrop->getHeight0()
            ));
        }

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $args["local_cache"] ?? $localCache;

        return $this->imageService->isCached($path, new BitmapFilter(null, $filters, $options), ["local_cache" => $localCache]);
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
}
