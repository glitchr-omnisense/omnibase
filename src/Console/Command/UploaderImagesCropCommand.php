<?php

namespace Base\Console\Command;

use Base\Annotations\Annotation\Uploader;
use Base\Annotations\AnnotationReader;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Entity\Layout\Image;
use Base\Entity\Layout\ImageCrop;
use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Imagine\Image\Box;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

#[AsCommand(name:'uploader:images:crop', aliases:[], description:'')]
class UploaderImagesCropCommand extends UploaderImagesCommand
{
    protected $input;
    protected $output;
    protected $maxDefinition;
    protected function configure(): void
    {
        $this->addOption('normalize', false, InputOption::VALUE_NONE, 'Do you want to update coordinate system ?');
        $this->addOption('max-definition', false, InputOption::VALUE_NONE, 'Which max definition to use ?');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->normalize     ??= $input->getOption('normalize');

        $this->maxDefinition ??= $input->getOption('max-definition');
        if($this->normalize) {

            if($this->maxDefinition == null) {

                $helper = $this->getHelper('question');
                $definitions = BaseBundle::getAllClasses(BaseBundle::getBundleLocation()."/Imagine/Filter/Basic/Definition");
                $question = new ChoiceQuestion('Please select a resolution class to be used for renormalization.', $definitions, false);

                $definition = $helper->ask($input, $output, $question);
                if($definition == null) return Command::FAILURE;

                $this->maxDefinition = new $definition();
            }
        }

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

        if($this->normalize) {

            $output->section()->writeln("\n <info>Looking for \"".ImageCrop::class."\"</info> to normalize..");

            $imageCrops = $this->imageCropRepository->findAll();
            $nNormalizable = 0;
            foreach($imageCrops as $imageCrop)
                if(!$imageCrop->isNormalized()) $nNormalizable++;

            $iProcess = 0;
            $iProcessAndNormalized = 0;
            $nTotalCrops = count($imageCrops);

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(' You are about to normalize '.$nNormalizable.' element(s) using '.class_basename(get_class($this->maxDefinition)).', do you want to continue? [y/n] ', false);
            if (!$helper->ask($input, $output, $question))
                return Command::FAILURE;


            foreach($imageCrops as $imageCrop) {

                $output->section()->writeln("             <warning>* Image #".$imageCrop->getImage()->getId()." \"".$imageCrop->getLabel()."\" .. (".($iProcess+1)."/".$nTotalCrops.")</warning>", OutputInterface::VERBOSITY_VERBOSE);

                if(!$imageCrop->isNormalized()) {

                    $naturalWidth  = $imageCrop->getNaturalWidth();
                    $naturalHeight = $imageCrop->getNaturalHeight();

                    $box = new Box($imageCrop->getNaturalWidth(), $imageCrop->getNaturalHeight());
                    $box = $this->maxDefinition->resize($box);

                    $naturalWidth = $box->getWidth();
                    $naturalHeight = $box->getHeight();

                    $x = $imageCrop->getX();
                    $imageCrop->setX0($x/$naturalWidth);
                    $y = $imageCrop->getY();
                    $imageCrop->setY0($y/$naturalHeight);
                    $width  = $imageCrop->getWidth();
                    $imageCrop->setWidth0($width/$naturalWidth);
                    $height = $imageCrop->getHeight();
                    $imageCrop->setHeight0($height/$naturalHeight);

                    $this->entityManager->flush($imageCrop);
                    $iProcessAndNormalized++;
                }

                $iProcess++;
            }
            if($iProcessAndNormalized) {

                $output->section()->writeln("");
                $msg = ' [OK] Nothing to update - image crops are already in sync & normalized. ';
                $output->writeln('<info,bkg>'.str_blankspace(strlen($msg)));
                $output->writeln($msg);
                $output->writeln(str_blankspace(strlen($msg)).'</info,bkg>');
                $output->section()->writeln("");

            } else {

                $msg = ' [OK] '.$nTotalCrops.' image crops found: '.$iProcess.' crop(s) processed ; '.$iProcessAndNormalized.' crop(s) renormalized !';
                $output->writeln('');
                $output->writeln('<info,bkg>'.str_blankspace(strlen($msg)));
                $output->writeln($msg);
                $output->writeln(str_blankspace(strlen($msg)).'</info,bkg>');
                $output->writeln('');
            }
        }

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

                    $msg = ' [WARN] Batch limit reached out - Program stopped for cache memory reason. Set the `--batch` limit higher, if you wish. ';
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
