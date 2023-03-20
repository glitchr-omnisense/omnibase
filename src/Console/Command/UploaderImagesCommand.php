<?php

namespace Base\Console\Command;

use Base\Annotations\Annotation\Uploader;
use Base\Controller\UX\FileController;
use Base\Imagine\Filter\Format\WebpFilter;
use Base\Service\ImageServiceInterface;
use Base\Service\LocalizerInterface;
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

    /**
     * @var bool
     */
    protected bool $cache = false;

    /**
     * @var bool
     */
    protected bool $warmup = false;

    protected $defaultFormats = [];
    public function __construct(
        LocalizerInterface $localizer,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        ImageServiceInterface $imageService,
        FileController $fileController
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
        $this->fileController = $fileController;

        $this->imageService   = $imageService;
        $this->imageService->setController($fileController);

        $this->defaultFormats = $parameterBag->get("base.uploader.formats");
    }

    protected function configure(): void
    {
        $this->addOption('warmup', false, InputOption::VALUE_NONE, 'Do you want to warm up image crops ?');
        $this->addOption('batch', false, InputOption::VALUE_OPTIONAL, 'Process data by batch of X entries', false);
        $this->addOption('format', false, InputOption::VALUE_OPTIONAL, 'Process data by batch of X entries');
        $this->addOption('cache', false, InputOption::VALUE_OPTIONAL, 'Cache data');
        parent::configure();
    }

    protected $input;
    protected $output;
    protected $batch;
    protected $warmer;
    protected $format;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityName  ??= str_strip($input->getOption('entity'), ["App\\Entity\\", "Base\\Entity\\"]);
        $this->warmup      ??= $input->getOption('warmup');
        $this->batch       ??= $input->getOption('batch');
        $this->format      ??= $input->getOption('format');
        $this->cache       ??= $input->getOption('cache');

        $this->appEntities ??= "App\\Entity\\".$this->entityName;
        $this->baseEntities ??= "Base\\Entity\\".$this->entityName;

        $this->input   = $input;
        $this->output  = $output;

        return parent::execute($this->input, $this->output);
    }

    public function isCached($data)
    {
        $args = $this->imageService->resolve($data);
        if (!$args) {
            return false;
        }

        $options = $args["options"] ?? [];
        $filters = $args["filters"] ?? [];

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $this->localCache ?? $args["local_cache"] ?? $localCache;

        $extensions = $this->imageService->getExtensions($args["path"] ?? $data);
        $extension  = first($extensions);

        $output = pathinfo_extension($data."/image", $extension);
        return $this->imageService->isCached($args["path"] ?? $data, new WebpFilter(null, $filters, $options), ["webp" => true, "local_cache" => $localCache, "output" => $output]);
    }

    protected $ibatch = 0;
    public function postProcess(mixed $class, string $field, Uploader $annotation, array $fileList)
    {
        if ($this->warmup) {
            if (!$annotation->isImage()) {
                $this->output->section()->writeln("             <warning>* Only images (mimetype = \"".implode(",", $annotation->mimeTypes())."\") can be warmed up.. </warning>");
                return;
            }

            $formats = [];

            $annotationFormats = $annotation->getFormats();
            foreach ($annotationFormats as $format) {
                if (!is_array($format)) {
                    $format = explode("x", $format);
                }
                if (count($format) != 2) {
                    $this->output->section()->writeln("             <warning>* Unexpected format provided ".implode("x", $format)."</warning>");
                    continue;
                }

                if ($this->format !== null && $this->format != implode("x", $format)) {
                    continue;
                }

                $formats[] = [(int) $format[0], (int) $format[1]];
            }

            foreach ($this->defaultFormats as $format) {
                if (!array_key_exists("width", $format)) {
                    $this->output->section()->writeln("             <warning>* Width information missing in default configuration \"".serialize($format)."\"</warning>");
                    continue;
                }
                if (!array_key_exists("height", $format)) {
                    $this->output->section()->writeln("             <warning>* Height information missing in default configuration \"".serialize($format)."\"</warning>");
                    continue;
                }

                if (array_key_exists("class", $format) && !is_instanceof($class, $format["class"])) {
                    continue;
                }
                if (array_key_exists("property", $format) && !preg_match("/^".$format["property"]."$/", $field)) {
                    continue;
                }

                $format = [$format["width"], $format["height"]];
                if ($this->format !== null && $this->format != implode("x", $format)) {
                    continue;
                }

                $formats[] = $format;
            }

            $N = count($fileList);
            for ($i = 0; $i < $N; $i++) {
                if ($this->ibatch >= $this->batch && $this->batch > 0) {
                    if ($i == 0) {
                        return;
                    }
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

                $formatStr = implode(", ", array_map(fn ($f) => implode("x", $f), $formats));
                $formatStr = $formatStr ? "Formats: ".$formatStr : "";

                $extensions = $this->imageService->getExtensions($file);
                $extension  = first($extensions);

                $data = $this->imageService->imagine($file, [], ["webp" => false, "local_cache" => true, "extension" => $extension]);
                if ($this->isCached($data)) {
                    $this->output->section()->writeln("             <warning>* Already cached \".".str_lstrip(realpath($file), realpath($publicDir))."\".. (".($i+1)."/".$N.")</warning>", OutputInterface::VERBOSITY_VERBOSE);
                } else {
                    $this->output->section()->writeln("             <info>* Warming up \".".str_lstrip(realpath($file), realpath($publicDir))."\".. (".($i+1)."/".$N.")</info>", OutputInterface::VERBOSITY_VERBOSE);
                    $this->ibatch++;

                    $data = $this->imageService->imagine($file, [], ["webp" => false, "local_cache" => true, "warmup" => ($this->cache != null), "extension" => $extension]);
                    $data = $this->imageService->imagine($file, [], ["webp" => true , "local_cache" => true, "warmup" => ($this->cache != null)]);

                    foreach ($formats as $format) {
                        list($width, $height) = $format;
                        $data = $this->imageService->thumbnail($file, $width, $height, [], ["webp" => false, "local_cache" => true, "warmup" => ($this->cache != null), "extension" => $extension]);
                        $data = $this->imageService->thumbnail($file, $width, $height, [], ["webp" => true , "local_cache" => true, "warmup" => ($this->cache != null)]);
                    }
                }

                $this->output->section()->writeln("                - Memory usage: ".round(memory_get_usage()/1024/1024)."MB; File: ".implode(", ", $annotation->mimeTypes())." (incl. WEBP); ".$formatStr, OutputInterface::VERBOSITY_VERY_VERBOSE);
            }
        }
    }
}
