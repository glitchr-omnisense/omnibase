<?php

namespace Base\Console\Command;

use Base\Console\Command;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Serializer\Encoder\ExcelEncoder;

use Base\Database\Factory\EntityHydrator;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;

use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class DoctrineDatabaseImportCommand extends Command
{
    protected static $defaultName = 'doctrine:database:import';
    protected static $defaultDescription = "This command allows to import data from an XLS file into the database"; 

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var EntityHydrator
     */
    protected $entityHydrator;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(EntityManagerInterface $entityManager, EntityHydrator $entityHydrator, ClassMetadataManipulator $classMetadataManipulator, TranslatorInterface $translator)
    {
        parent::__construct();

        $this->entityManager  = $entityManager;
        $this->entityHydrator = $entityHydrator;
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->translator = $translator;
        $this->serializer = new Serializer([], [
            new XmlEncoder(), 
            new CsvEncoder(), 
            new ExcelEncoder()
        ]);
    }

    public function normalize($entityName, $entityData)
    {
        return array_inflate(".", array_transforms(function($propertyPath, $entry, $fn, $i) use ($entityName):array {

            $propertyPath = trim(explode('\n', $propertyPath)[0]); // Only keeps headline
            $fieldName = explodeByArray([":", "["], $propertyPath)[0];

            if (preg_match('/(.*)\:explode\((.)\)(.*)/', $propertyPath, $matches)) {

                $propertyPath    = $matches[1].$matches[3] ?? "";
                $entrySeparator = $matches[2];

                if(!is_array($entry))
                    $entry = array_map(fn($v) => $v ? trim($v) : ($v === "" ? null : $v), explode($entrySeparator, $entry));
            }

            $propertyName = preg_replace("/\:[^\.]*/", "", $propertyPath);
            if(substr_count($propertyName, "[") > 1)
                throw new Exception("Backets \"[]\" are expected to appear only once at the end.. \"".$propertyName."\"");
            
            if (preg_match('/(.+)(?:\[(.*?)\])(?:\((.)\))*(.*)/', $propertyPath, $matches)) {

                $propertyPath = str_replace(["[", "]"], [".", ""], $matches[1].$matches[4] ?? "");
                $propertyName = $matches[2] ?? null;
                $fieldSeparator = $matches[3] ?? null;

                $subFieldName = preg_replace("/\:[^\.]*/", "", $propertyPath);
                $entityMapping = $this->classMetadataManipulator->fetchEntityMapping($entityName, $subFieldName);
                $classMetadata = $this->entityManager->getClassMetadata($entityName);
                if(!$classMetadata->hasAssociation($classMetadata->getFieldName($subFieldName))) 
                    throw new Exception("Field \"".$subFieldName."\" is expected to be an association.");

                $isToManySide = in_array($entityMapping["type"], [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY], true);
                if(!is_array($entry)) $entry = [$propertyName => $entry];
                else if($isToManySide) {

                    foreach($entry as &$e) {

                        if(!$fieldSeparator) $e = [$propertyName => $e];
                        else {

                            $propertyNames = explode(",", $propertyName);
                            $entries = explode($fieldSeparator, $e, count($propertyNames));

                            $e = [];
                            foreach($propertyNames as $i => $p)
                                $e[trim($p)] = array_key_exists($i, $entries) && $entries[$i] ? trim($entries[$i]) : null;
                        }
                    }

                }
            }

            if(is_array($entry)) {

                $entry = array_inflate(".", $entry);
                if($fieldName != $propertyName) {

                    $entry = array_transforms($fn, [$propertyPath => $entry]);
                    $propertyPath = array_key_first($entry);
                
                    $entry = first($entry);
                }
            }

            return [$propertyPath, $entry];

        }, $entityData));
    }

    protected function configure(): void
    {
        $this->addArgument('path',            InputArgument::OPTIONAL    , 'Path or URL');
       
        $this->addOption('spreadsheet', null, InputOption::VALUE_OPTIONAL, 'Import selected spreadsheet (e.g. 1,3,4 [NB: starts from 1])');
        $this->addOption('extension',   null, InputOption::VALUE_OPTIONAL, 'Specify file extension to be used');
        $this->addOption('rows',        null, InputOption::VALUE_OPTIONAL, 'Only read N rows');
        $this->addOption('show',        null, InputOption::VALUE_NONE, 'Show all details');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if($input->hasArgument("path")) $path = $input->getArgument("path");
        
        $show            = $input->getOption("show");
        $nRows           = $input->getOption("rows");
        $extension       = $input->getOption("extension");
        $spreadsheetKeys = $input->getOption("spreadsheet") !== null ? explode(",", $input->getOption("spreadsheet")) : null;

        $output->writeln("");
        if($path) $output->writeln(' <info>You have just selected:</info> '.$path);
        else {

            $helper   = $this->getHelper('question');
            $question = new Question(' > ');

            $output->writeln(' <info>Please enter the location of your file (either local path or url)</info>: ');
            $path = $helper->ask($input, $output, $question);
        }
        $output->writeln("");

        $output->writeln(' Decoding spreadsheets..');        
        $mimeTypes = new MimeTypes();
        $extension = $extension ?? $mimeTypes->getExtensions(mime_content_type2($path))[0] ?? null;
        switch($extension)
        {
            case "xml":
            case "xls": case "xlsx": case "xlsm":
                $rawData = $this->serializer->decode(file_get_contents2($path), $extension);
                break;

            case "csv": case "txt":
                $rawData = $this->serializer->decode(file_get_contents2($path), 'csv');

            default:
                throw new Exception("Missing extension in filename. Please use \"--extension\" option.");
        }

        $baseClass = [];
        $entityData = [];
        $existingEntityData = [];
        $newEntityData = [];

        // Shrink and restrict according to options
        $keys = [];
        if($spreadsheetKeys !== null) 
            $keys = array_filter(array_keys($rawData), fn($id) => !in_array($id, $spreadsheetKeys), ARRAY_FILTER_USE_KEY);

        $rawData = array_key_removes($rawData, ...$keys);
        foreach($rawData as $spreadsheet => &$import) {

            // Remove comments: 2nd line
            array_shift($rawData[$spreadsheet]);

            $rawData[$spreadsheet] = array_limit($rawData[$spreadsheet], $nRows);
        }

        // Process spreadsheet
        $output->writeln(' Normalizing rows..');
        foreach($rawData as $spreadsheet => &$import) {

            $entityData[$spreadsheet] = [];
            $existingEntityData[$spreadsheet] = [];
            $existingEntityData[$spreadsheet] = [];
            $newEntityData[$spreadsheet] = [];

            //
            // Import type
            $className = array_key_first($import[0] ?? []);
            $baseClass[$spreadsheet] =  $className;
            if(!$this->classMetadataManipulator->isEntity($className)) {
                $output->section()->writeln(" <warning>* Spreadsheet \"$spreadsheet\" is ignored, no valid entity found</warning>\n");
                continue;
            }

            $discriminatorMap = $this->entityManager->getClassMetadata($className)->discriminatorMap ?? [];

            //
            // Clean up empty fields
            $beforeKeys = array_keys($rawData[$spreadsheet]);
            $rawData[$spreadsheet] = array_filter_recursive($rawData[$spreadsheet]);
            $afterKeys = array_keys($rawData[$spreadsheet]);
            
            foreach($rawData[$spreadsheet] as &$data) {

                if($discriminatorMap) {

                    $discriminatorEntry = $data[$className] ?? array_flip($discriminatorMap)[$className];
                    $entityName = $discriminatorMap[$data[$className] ?? array_flip($discriminatorMap)[$className]] ?? null;                
            
                    if(is_a($className, first($discriminatorMap))) throw new Exception("Entity \"".$className."\" doesn't inherit from \"".first($discriminatorMap)."\""); 
                    else if(!array_key_exists($discriminatorEntry, $discriminatorMap)) throw new Exception("Discriminatory entry \"".$discriminatorEntry."\" not found in the discriminator list of \"".first($discriminatorMap)."\".. something wrong ?");
                }
            
                unset($data[$className]);
                $entityData[$spreadsheet][$entityName][] = $this->normalize($entityName, $data);
            }
        }

        $output->writeln(' Hydrating entities..');
        foreach($rawData as $spreadsheet => &$import) {

            foreach($entityData[$spreadsheet] ?? [] as $entityName => &$_) {

                //
                // Loop over entries
                foreach($_ as &$entry) {

                    $keyDepth = [];
                    $entityDepth = [];
                    $inDatabase = false;
                    $entry = array_transforms(function($k,$v,$fn,$i,$d) use ($entityName, &$entityDepth, &$keyDepth, &$inDatabase) : ?array {

                        list($fieldName, $special) = array_pad(explode(":", $k, 2),2,null);
                        $keyDepth[$d] = $fieldName;
                        
                        $fieldPath = implode(".", $keyDepth);
                        $targetName = $this->classMetadataManipulator->fetchEntityName($entityName, $fieldPath);
                        $entityDepth[$d] = $targetName;
                        if(is_array($v)) $v = array_transforms($fn, $v, $d+1);

                        if(!$v) {

                            array_pop($keyDepth);
                            array_pop($entityDepth);

                            return $d > 5 ? null : [$fieldName, null];
                        }

                        if ($special) {

                            $resolvedFieldPath = $this->classMetadataManipulator->resolveFieldPath($entityName, $fieldPath);
                            if($resolvedFieldPath === null) throw new Exception("Cannot resolve field path \"$fieldPath\" for \"$entityName\"");
                            
                            $fieldName = explode(".", $resolvedFieldPath);
                            $fieldName = end($fieldName);
                            
                            $entityName = $entityDepth[$d-1] ?? $entityName;

                            $mapping = $this->classMetadataManipulator->getMapping($entityName, $fieldName);
                            if ($entityName) {

                                $entityRepository = $this->entityManager->getRepository($entityName);
                                
                                switch ($special) {
                                    case "find":

                                        if($this->classMetadataManipulator->hasAssociation($entityName, $fieldName)) {
                                            
                                            $targetRepository = $this->entityManager->getRepository($mapping["targetEntity"]);
                                            $isToOneSide = in_array($mapping["type"], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE], true);

                                            if($isToOneSide) $v = $targetRepository->findOneBy($v);
                                            else $v = array_filter(array_map(fn($e) => $targetRepository->findOneBy($e), $v));
                                        }

                                        break;

                                    case "unique":
                                            
                                            if($this->classMetadataManipulator->hasAssociation($entityName, $fieldName))
                                                $inDatabase |= ($entityRepository->findOneBy($v) !== null);
                                            else if($this->classMetadataManipulator->hasField($entityName, $fieldName))
                                                $inDatabase |= ($entityRepository->findOneBy([$fieldName => $v]) !== null);
                                        break;

                                    case "enum":

                                        foreach($v as $className => $key) {

                                            $className = substr($className, 1, strlen($className)-2);
                                            if(is_instanceof($className, EnumType::class))
                                                $v[$className] = $className::getValue($key);
                                            else if(is_instanceof($className, SetType::class))
                                                $v[$className] = array_map(fn($k) => $className::getValue($k), explode(",", $key));
                                            else throw new Exception("Class must be either ".EnumType::class." or ".SetType::class);
                                        }

                                        break;
                                }
                            }
                        }

                        array_pop($keyDepth);
                        array_pop($entityDepth);

                        return $v ? [$fieldName, $v] : null;

                    }, $entry);

                    $aggregateModel = EntityHydrator::CLASS_METHODS|EntityHydrator::OBJECT_PROPERTIES;
                    if ($entry) {

                        $entity = $this->entityHydrator->hydrate($entityName, $entry, [], $aggregateModel);
                        
                        if($inDatabase) $existingEntityData[$spreadsheet][$entityName][] = $entity;
                        else $newEntityData[$spreadsheet][$entityName][] = $entity;
                    }
                }
            }
        }

        $output->writeln(' <info>New data found: </info>'.implode(", ", array_map(function($spreadsheet) use ($baseClass, $entityData, $newEntityData) {
          
            $countData = 0;
            foreach(array_keys($entityData[$spreadsheet]) as $className)
                $countData    += count($entityData[$spreadsheet][$className] ?? []);

            $countNewData = 0;
            foreach(array_keys($newEntityData[$spreadsheet]) as $className)
                $countNewData += count($newEntityData[$spreadsheet][$className] ?? []);

            $count = $countData > 0 ? $countNewData."/".$countData : "0";
            $plural       = ($countNewData > 1);

            return $count." <ln>".lcfirst($this->translator->entity($baseClass[$spreadsheet], $plural ? Translator::TRANSLATION_PLURAL : Translator::TRANSLATION_SINGULAR)) .'</ln>';

        }, array_keys(array_filter($entityData)))));

        if($show) {

            foreach($entityData as $spreadsheet => $_) {

                $output->writeln("\n * <info>Spreadsheet \"".$spreadsheet."\"</info>");
                foreach($_ as $className => $_) {

                    if($existingEntityData[$spreadsheet][$className] ?? false) {
                        foreach($existingEntityData[$spreadsheet][$className] as $entry)
                            $output->writeln("\t<warning>".$className.": </warning>\"". $entry."\" found in database");
                    }
                    if($newEntityData[$spreadsheet][$className] ?? false) {
                        foreach($newEntityData[$spreadsheet][$className] as $entry)
                            $output->writeln("\t<ln>".$className.": </ln>\"". $entry."\" is ready for import !");
                    }
                }
            }
        }

        $output->writeln("");

        $helper   = $this->getHelper('question');
        $question = new Question(' > ');

        if(empty(array_filter($newEntityData))) {

            $msg = ' [OK] Nothing to update - your database is already in sync with the current dataset. ';
            $output->writeln('<info,bkg>'.str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)).'</info,bkg>');

        } else {

            $output->writeln(' <info>Do you want to import these entries into the database ? (yes/no)</info> [<warning>no</warning>]: ');
            $apply = $helper->ask($input, $output, $question);

            if(strtolower($apply) != "y" && strtolower($apply) != "yes")
                return Command::FAILURE;

            foreach($newEntityData as $spreadsheet => &$_) {
                foreach($_ as $className => $entries) {

                    foreach($entries as $entry) $this->entityManager->persist($entry);
                    $this->entityManager->flush();
                }
            }

            $output->section()->writeln("\n <warning>/!\\ This dataset has been imported into database..</warning>");
        }

        $output->section()->writeln("");
        return Command::SUCCESS;
    }
}
