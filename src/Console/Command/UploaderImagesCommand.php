<?php

namespace Base\Console\Command;

use Base\Annotations\Annotation\Uploader;
use Base\Cache\UploadWarmer;
use Base\Controller\UX\FileController;
use Base\Entity\Layout\Image;
use Base\Imagine\Filter\Format\WebpFilter;
use Base\Service\ImageServiceInterface;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'uploader:images', aliases:[], description:'')]
class UploaderImagesCommand extends UploaderEntitiesCommand
{
    /**
     * @var ImageServiceInterface
     */
    protected $imageService;

    /**
     * @var FileController
     */
    protected $fileController;

    public function __construct(
        LocaleProviderInterface $localeProvider, TranslatorInterface $translator, EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag,
        ImageServiceInterface $imageService, FileController $fileController)
    {
        parent::__construct($localeProvider, $translator, $entityManager, $parameterBag);
        $this->imageService   = $imageService;
        $this->fileController = $fileController;
    }

    protected function configure(): void
    {
        $this->addOption('warmup', false, InputOption::VALUE_NONE, 'Do you want to warm up image crops ?');
        $this->addOption('batch' , false, InputOption::VALUE_OPTIONAL, 'Process data by batch of X entries', false);
        parent::configure();
    }

    protected $input;
    protected $output;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityName  ??= str_strip($input->getOption('entity'), ["App\\Entity\\", "Base\\Entity\\"]);
        $this->warmup      ??= $input->getOption('warmup');
        $this->batch       ??= $input->getOption('batch');

        $this->appEntities ??= "App\\Entity\\".$this->entityName;
        $this->baseEntities ??= "Base\\Entity\\".$this->entityName;

        $this->input  = $input;
        $this->output = $output;

        return parent::execute($this->input, $this->output);
    }

    public function isCached($hashid)
    {
        $args = $this->imageService->resolve($hashid);
        if(!$args) return false;

        $options = $args["options"] ?? [];
        $filters = $args["filters"] ?? [];

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $args["local_cache"] ?? $localCache;

        return $this->imageService->isCached($args["path"], new WebpFilter(null, $filters, $options), ["webp" => true, "local_cache" => $localCache]);
    }

    protected $ibatch = 0;
    public function postProcess(mixed $class, string $field, Uploader $annotation, array $fileList)
    {
        if($this->warmup) {

            if(!$annotation->isImage()) {

                $this->output->section()->writeln("             <warning>* Only images (mimetype = \"".implode(",", $annotation->mimeTypes())."\") can be warmed up.. </warning>");
                return;
            }

            $formats = $annotation->getFormats();
            foreach($formats as $key => $format) {

                if(!is_array($format)) $format = explode("x", $format);
                if(count($format) != 2) {
                    $this->output->section()->writeln("             <warning>* Unexpected format provided ".implode("x", $format)."</warning>");
                    continue;
                }

                $formats[$key] = $format;
            }

            $N = count($fileList);
            for($i = 0; $i < $N; $i++) {

                if($this->ibatch >= $this->batch && $this->batch > 0) {

                    if($i == 0) return;
                    $msg = ' [WARN] Batch limit reached out - Set the `--batch` limit higher, if you wish. ';
                    $this->output->writeln('');
                    $this->output->writeln('<warning,bkg>'.str_blankspace(strlen($msg)));
                    $this->output->writeln($msg);
                    $this->output->writeln(str_blankspace(strlen($msg)).'</warning,bkg>');
                    $this->output->writeln('');

                    return;
                }

                $file = $fileList[$i];
                $publicDir = $annotation->getFlysystem()->getPublic("", $annotation->storage());

                $formatStr = implode(", ", array_map(fn($f) => implode("x", $f), $formats));
                $formatStr = $formatStr ? "Formats: ".$formatStr : "";

                if($this->isCached($file)) {

                    $this->output->section()->writeln("             <warning>* Already cached \"".str_lstrip($file,$publicDir)."\".. (".($i+1)."/".$N.")</warning>", OutputInterface::VERBOSITY_VERBOSE);

                } else {

                    $this->output->section()->writeln("             <ln>* Warming up \"".str_lstrip($file,$publicDir)."\".. (".($i+1)."/".$N.")</ln>", OutputInterface::VERBOSITY_VERBOSE);
                    $this->ibatch++;

                    $extensions = $this->imageService->getExtensions($file);
                    $extension  = first($extensions);

                    $hashid = basename(dirname($this->imageService->imagine($file, [], ["webp" => false])));
                    $this->fileController->Image($hashid, $extension);
                    $hashid = basename(dirname($this->imageService->imagine($file, [], ["webp" => true])));
                    $this->fileController->ImageWebp($hashid);

                    foreach($formats as $format) {

                        list($width, $height) = $format;
                        $hashid = basename(dirname($this->imageService->thumbnail($file, $width, $height, [], ["webp" => false])));
                        $this->fileController->Image($hashid, $extension);
                        $hashid = basename(dirname($this->imageService->thumbnail($file, $width, $height, [], ["webp" => true])));
                        $this->fileController->ImageWebp($hashid);
                    }
                }

                $this->output->section()->writeln("                - Memory usage: ".round(memory_get_usage()/1024/1024)."MB; File: ".implode(", ", $annotation->mimeTypes())." (incl. WEBP); ".$formatStr, OutputInterface::VERBOSITY_VERY_VERBOSE);
            }
        }
    }
}
