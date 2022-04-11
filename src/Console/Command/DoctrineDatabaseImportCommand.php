<?php

namespace Base\Console\Command;

use Base\Console\Command;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Serializer\Encoder\ExcelEncoder;

use Base\Database\Factory\EntityHydrator;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
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
        return  array_inflate(".", array_transforms(function($propertyPath, $entry, $fn, $i) use ($entityName):array {

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
                $entityMapping = $this->entityHydrator->fetchEntityMapping($entityName, $subFieldName);
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
                                $e[$p] = $entries[$i] ?? null;
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
        $this->addArgument('path',           InputArgument::OPTIONAL    , 'Path or URL');

        $this->addOption('import',      null, InputOption::VALUE_NONE, 'Import selected data into database');
        $this->addOption('show',       null, InputOption::VALUE_NONE, 'Show all details');

        parent::configure();
    }

    public function extension(string $path) {

        try { $extension = exif_imagetype($path); }
        catch (Exception $e) { $extension = false; }
        return $extension !== false ? mb_substr(image_type_to_extension($extension), 1) : pathinfo($path, PATHINFO_EXTENSION) ?? null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if($input->hasArgument("path")) $path = $input->getArgument("path");

        $show = $input->getOption("show");

        $output->writeln("");
        if($path) $output->writeln(' <info>You have just selected:</info> '.$path);
        else {

            $helper   = $this->getHelper('question');
            $question = new Question(' > ');

            $output->writeln(' <info>Please enter the location of your file (either local path or url)</info>: ');
            $path = $helper->ask($input, $output, $question);
        }
        $output->writeln("");

        $extension = $this->extension($path);
        switch($extension)
        {
            case "xml":
            case "xls": case "xlsx": case "xlsm":
                $rawData = $this->serializer->decode(file_get_contents($path), $extension);
                break;

            case "csv": case "txt": default:
                $rawData = $this->serializer->decode(file_get_contents($path), 'csv');
        }

        $baseClass = [];
        $entityData = [];
        $entityGroups = [];
        $existingEntityData = [];
        $newEntityData = [];
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
            
            // Remove comments: 2nd line
            array_shift($rawData[$spreadsheet]);

            //
            // Clean up empty fields
            $beforeKeys = array_keys($rawData[$spreadsheet]);
            $rawData[$spreadsheet] = array_filter_recursive($rawData[$spreadsheet]);
            $afterKeys = array_keys($rawData[$spreadsheet]);
            $entityGroups[$spreadsheet] = array_diff($beforeKeys, $afterKeys);

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

        $allData = [];
        foreach($rawData as $spreadsheet => &$import) {

            foreach($entityData[$spreadsheet] ?? [] as $entityName => &$_) {

                //
                // Determine the expected outgoing entity
                $entityRepository = $this->entityManager->getRepository($entityName);
                $classMetadata = $this->entityManager->getClassMetadata($entityName);

                //
                // Collecting existing data for comparison
                $allData[$entityName] = $allData[$entityName] ?? $entityRepository->findAll();

                //
                // Loop over entries
                foreach($_ as &$entry) {

                    //
                    // Look up for existing entities and replace
                    $entry = array_key_flattens(".", $entry);
                    $entry = array_key_explodes([":find.", ":find"], $entry);
                    foreach($entry as $propertyPath => &$association) {

                        $fieldName = $classMetadata->getFieldName(preg_replace("/\:[^\.]*/", "", $propertyPath));
                        if(is_array($association)) {

                            if(($associationMapping = $this->entityHydrator->fetchEntityMapping($entityName, $fieldName))) {

                                $associationName = $associationMapping["targetEntity"];
                                $associationRepository = $this->entityManager->getRepository($associationName);
                                $association = array_inflate(".", $association);
                                
                                $isToManySide = in_array($associationMapping["type"], [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY], true);
                                if($isToManySide) {
                                    
                                    foreach($association as $i => $associationEntry)
                                        $association[$i] = $associationRepository->findOneBy($associationEntry);

                                    $association = array_filter($association);

                                } else {

                                    $association = $associationRepository->findOneBy($association);
                                    if($association === null) unset($entry[$propertyPath]);
                                }

                                continue;
                            } 
                        }
                    }

                    $entry = array_inflate(".", $entry);
                    $entry = array_key_flattens(".", $entry);
                    $entry = array_key_explodes([":unique.", ":unique"], $entry);

                    $inDatabase = false;
                    foreach($entry as $propertyPath => &$field) {

                        $fieldName = $classMetadata->getFieldName(preg_replace("/\:[^\.]*/", "", $propertyPath));

                        if(is_array($field)) {

                            $field = array_inflate(".", $field);
                            if($this->entityHydrator->fetchEntityMapping($entityName, $fieldName)) {

                                if($classMetadata->hasField($fieldName)) {
                                    $field = first($field);
                                    $inDatabase |= ($entityRepository->findOneBy([$fieldName => $field]) !== null);
                                    continue;
                                } else if($classMetadata->hasAssociation($fieldName)) {
                                    $inDatabase |= ($entityRepository->findOneBy(array_inflate(".", $field)) !== null);
                                    continue;
                                }
                            }
                        }
                    }

                    $entry = array_inflate(".", $entry);
                    
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
                            $output->writeln("\t<ln>".$className.": </ln>\"". $entry."\"");
                    }
                }
            }
        }

        $output->writeln("");

        $helper   = $this->getHelper('question');
        $question = new Question(' > ');

        $output->writeln(' <info>Do you want to import these entries into the database ? (yes/no)</info> [<warning>no</warning>]: ');
        $apply = "Y" ;//$helper->ask($input, $output, $question);

        if(strtolower($apply) != "y" && strtolower($apply) != "yes") {
            $output->section()->writeln("\n<warning>Skip.</warning>");
            return Command::FAILURE;
        }

        $output->section()->writeln("\n <warning>/!\\ These settings have been applied into database..</warning>");

        $output->section()->writeln("");
        return Command::SUCCESS;
    }
}
