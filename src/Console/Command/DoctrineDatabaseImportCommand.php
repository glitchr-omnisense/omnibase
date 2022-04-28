<?php

namespace Base\Console\Command;

use Base\Console\Command;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Serializer\Encoder\ExcelEncoder;

use Base\Database\Factory\EntityHydrator;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Entity\Thread;
use Base\Service\BaseService;
use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
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

    public function __construct(EntityManagerInterface $entityManager, EntityHydrator $entityHydrator, ClassMetadataManipulator $classMetadataManipulator, TranslatorInterface $translator, BaseService $baseService)
    {
        parent::__construct();

        $this->baseService    = $baseService;
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
        return array_inflate(".", array_transforms(function($propertyPath, $entry, $fn, $i) use ($entityName):?array {

            $propertyPath = trim(explode('\n', $propertyPath)[0]); // Only keeps headline
            $fieldName = explodeByArray([":", "["], $propertyPath)[0];

            if (preg_match('/(.*)\:explode\((.)\)(.*)/', $propertyPath, $matches)) {

                $propertyPath    = $matches[1].$matches[3] ?? "";
                $entrySeparator = $matches[2];

                if(!is_array($entry))
                    $entry = array_map(fn($v) => $v ? trim($v) : ($v === "" ? null : $v), explode($entrySeparator, str_rstrip(trim($entry), $entrySeparator)));
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

            return $propertyPath ? [$propertyPath, $entry] : null;

        }, $entityData));
    }


    protected function configure(): void
    {
        $this->addArgument('path',            InputArgument::OPTIONAL    , 'Path or URL');
       
        $this->addOption('spreadsheet', null, InputOption::VALUE_OPTIONAL, 'Import selected spreadsheet (e.g. 1,3,4 [NB: starts from 1])');
        $this->addOption('extension',   null, InputOption::VALUE_OPTIONAL, 'Specify file extension to be used');
        $this->addOption('nrows',       null, InputOption::VALUE_OPTIONAL, 'Only read N-rows', null);
        $this->addOption('iskip',       null, InputOption::VALUE_OPTIONAL, 'Skip i-entries', 0);
        $this->addOption('batch',       null, InputOption::VALUE_OPTIONAL, 'Persist by batch of X entries.', 25);
        $this->addOption('show',        null, InputOption::VALUE_NONE, 'Show all details');
        $this->addOption('notify',      null, InputOption::VALUE_NONE, 'Send notification if needed');
        $this->addOption('force',       null, InputOption::VALUE_NONE, 'Import without asking confirmation.');
        $this->addOption('on-fly',       null, InputOption::VALUE_NONE, 'Import on the fly (while hydratation)');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if($input->hasArgument("path")) $path = $input->getArgument("path");
        
        $notify            = $input->getOption("notify");
        if(!$notify) $this->baseService->getNotifier()->disable();

        $show         = $input->getOption("show");
        $force        = $input->getOption("force");
        $extension    = $input->getOption("extension");
        $batch        = $input->getOption("batch");
        $onFly        = $input->getOption("on-fly");
        $spreadsheets = $input->getOption("spreadsheet") !== null ? explode(",", $input->getOption("spreadsheet")) : null;
        $nrows        = (int) $input->getOption("nrows");
        $iskip        = (int) $input->getOption("iskip");
        
        if(!$nrows) $nrows = null;
        
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

        $baseClass  = [];
        $entityData = [];
        $entityUniqueKeys = [];
        $existingEntities = [];
        $invalidEntities  = [];
        $newEntities      = [];

        // Shrink and restrict according to options
        $spreadsheetKeys = [];
        if($spreadsheets !== null) 
            $spreadsheetKeys = array_filter(array_keys($rawData), fn($id) => !in_array($id, $spreadsheets), ARRAY_FILTER_USE_KEY);

        $rawData = array_key_removes($rawData, ...$spreadsheetKeys);
        foreach($rawData as $spreadsheet => $_) {

            // Remove comments: 2nd line
            array_shift($rawData[$spreadsheet]);
        }

        // Process spreadsheet
        $output->writeln(' Normalizing rows..');
        $totalData = 0;
        $counter   = 0;
        foreach($rawData as $spreadsheet => $entry) {

            $parentThread = null;
            $entityData[$spreadsheet]       = [];
            $existingEntities[$spreadsheet] = [];
            $invalidEntities[$spreadsheet]  = [];
            $newEntities[$spreadsheet]      = [];
            $entityUniqueKeys[$spreadsheet] = [];

            //
            // Import type
            $baseName = array_key_first($entry[0] ?? []);
            $baseClass[$spreadsheet] =  $baseName;
            if(!$this->classMetadataManipulator->isEntity($baseName)) {
                $output->section()->writeln(" <warning>* Spreadsheet \"$spreadsheet\" is ignored, no valid entity found</warning>\n");
                continue;
            }

            $discriminatorMap = $this->entityManager->getClassMetadata($baseName)->discriminatorMap ?? [];

            //
            // Clean up empty fields
            $rawData[$spreadsheet] = array_filter_recursive($rawData[$spreadsheet], fn($d) => $d !== null);
            foreach($rawData[$spreadsheet] as &$data) {

                if($counter < $iskip || ($nrows !== null && $counter > $nrows+$iskip-1)) { 
                    $counter++;
                    continue;
                }

                if($discriminatorMap) {

                    $discriminatorEntry = $data[$baseName] ?? array_flip($discriminatorMap)[$baseName];
                    $entityName = $discriminatorMap[$data[$baseName] ?? array_flip($discriminatorMap)[$baseName]] ?? null;                
            
                    if(is_a($baseName, first($discriminatorMap))) throw new Exception("Entity \"".$baseName."\" doesn't inherit from \"".first($discriminatorMap)."\""); 
                    else if(!array_key_exists($discriminatorEntry, $discriminatorMap)) throw new Exception("Discriminatory entry \"".$discriminatorEntry."\" not found in the discriminator list of \"".first($discriminatorMap)."\".. something wrong ?");
                }
            
                unset($data[$baseName]);
                $entityData[$spreadsheet][$baseName][] = [$entityName, $this->normalize($entityName, $data)];

                $totalData++;
                $counter++;
            }
        }
        
        $output->writeln(' Hydrating entities..'.($onFly ? " and import them on the fly" : null));

        if($totalData) $progressBar = new ProgressBar($output, $totalData);

        $entityParentColumn = null;
        $entityUniqueValues = [];

        $counter = 0;
        foreach($rawData as $spreadsheet => $__) {

            foreach($entityData[$spreadsheet] ?? [] as $baseName => &$entries) {

                $output->writeln("\n * <info>Spreadsheet \"".$spreadsheet."\"</info>: $baseName");
                    
                //
                // Loop over entries
                foreach($entries as &$_) {

                    $progressBar->advance();
                    $counter++;

                    $keyDepth = [];
                    $targetEntity = [];
                    $inDatabase = false;

                    list($entityName, $entry) = $_;

                    $entry = array_transforms(function($k,$v,$fn,$i,$d) use ($spreadsheet, &$entityParentColumn, &$entityUniqueValues, $entityName, &$entityUniqueKeys, &$targetEntity, &$keyDepth, &$inDatabase) : ?array {

                        list($fieldName, $special) = array_pad(explode(":", $k, 2),2,null);
                        $keyDepth[$d] = $fieldName;
                        
                        $fieldPath = implode(".", $keyDepth);
                        $targetName = $this->classMetadataManipulator->fetchEntityName($entityName, $fieldPath);
                        $targetEntity[$d] = $targetName;

                        if(is_array($v)) $v = array_transforms($fn, $v, $d+1);

                        if(!$v && $v !== false) {

                            array_pop($keyDepth);
                            array_pop($targetEntity);

                            return $d > 2 ? null : [$fieldName, $v];
                        }

                        if(is_instanceof($entityName, Thread::class) && $this->classMetadataManipulator->getFieldName($entityName, $fieldName) == "parent")
                            $entityParentColumn = $fieldName;
                        
                        if ($special) {

                            $resolvedFieldPath = $this->classMetadataManipulator->resolveFieldPath($entityName, $fieldPath);
                            if($resolvedFieldPath === null) throw new Exception("Cannot resolve field path \"$fieldPath\" for \"$entityName\"");
                            
                            $fieldName = explode(".", $resolvedFieldPath);
                            $fieldName = end($fieldName);

                            $targetName = $targetEntity[$d-1] ?? $entityName;
                            if(!array_key_exists($targetName, $entityUniqueKeys[$spreadsheet]))
                                $entityUniqueKeys[$spreadsheet][$targetName] = [];

                            $mapping = $this->classMetadataManipulator->getMapping($targetName, $fieldName);
                            if ($targetName) {

                                if(!array_key_exists($targetName, $entityUniqueValues))
                                    $entityUniqueValues[$targetName] = [];
                                if(!array_key_exists($fieldName, $entityUniqueValues[$targetName]))
                                    $entityUniqueValues[$targetName][$fieldName] = [];

                                switch (explode("(", $special)[0]) {

                                    case "unique":
                                        $entityUniqueKeys[$spreadsheet][$entityName][] = [$targetName, $resolvedFieldPath];
                                        $entityUniqueValues[$targetName][$fieldName][] = $v;
                                        break;

                                    case "find":

                                        if($this->classMetadataManipulator->hasAssociation($targetName, $fieldName)) {

                                            $targetRepository = $this->entityManager->getRepository($mapping["targetEntity"]);
                                            $isToOneSide = in_array($mapping["type"], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE], true);

                                            $v = array_filter($v);
                                            if(empty($v)) $v = null;
                                            else {

                                                $vBak = $v;
                                                if($isToOneSide) $v = $targetRepository->findOneBy($vBak);
                                                else $v = array_filter(array_map(fn($e) => $targetRepository->findOneBy($e), $vBak));

                                                if($v === null) 
                                                    throw new Exception("Failed to find \"".$targetName."\" with parameters \"".serialize($vBak)."\"");
                                            }
                                        }

                                        break;

                                    case "enum":

                                        $special = str_lstrip($special, "enum(");
                                        $enumType = substr($special, 0, strlen($special)-1);
                                        
                                        $typeOfField = $this->classMetadataManipulator->getTargetClass($targetName, $fieldName);
                                        if(!is_instanceof($typeOfField, $enumType)) throw new Exception("Incompatibility between EnumType provided \"".$enumType."\" and database type \"".$typeOfField."\"");

                                        if (is_instanceof($enumType, SetType::class))
                                            $v = array_map(fn($k) => $enumType::getValue($k), $v);
                                        else if (is_instanceof($enumType, EnumType::class))
                                            $v = $enumType::getValue($v) ;
                                        else throw new Exception("Class must be either ".EnumType::class." or ".SetType::class);

                                        break;
                                }
                            }
                        }

                        array_pop($keyDepth);
                        array_pop($targetEntity);

                        return $v !== null ? [$fieldName, $v] : null;

                    }, $entry);

                    if ($entry) {
    
                        if(!array_key_exists($baseName, $existingEntities[$spreadsheet]))
                            $existingEntities[$spreadsheet][$baseName] = [];
                        if(!array_key_exists($baseName, $invalidEntities[$spreadsheet]))
                            $invalidEntities[$spreadsheet][$baseName] = [];
                        if(!array_key_exists($baseName, $newEntities[$spreadsheet]))
                            $newEntities[$spreadsheet][$baseName] = [];

                        $isInvalid = false;

                        $entity = $this->entityHydrator->hydrate($entityName, $entry, [], EntityHydrator::CLASS_METHODS|EntityHydrator::OBJECT_PROPERTIES);
                        if($entity instanceof Thread && $entityParentColumn !== null) {

                            if($entity->getParent()) $parentThread = $entity;
                            else {
                                
                                $entity->setParent($parentThread);
                                if (!in_array($parentThread, $newEntities[$spreadsheet][$baseName])) 
                                    $isInvalid = true;
                            }
                        }
                        
                        //
                        // Check if duplicates found in the processed list
                        foreach($entityUniqueKeys[$spreadsheet][$entityName] ?? [] as $_) {

                            list($targetName, $resolvedFieldPath) = $_;
                            $fieldPath = explode(".", $resolvedFieldPath);

                            // Extract target entity
                            $targetValue = [$entity];
                            foreach($fieldPath as $fieldName) {

                                $targetValue = array_flatten('.', array_map(function($e) use ($fieldName) {

                                    $e = $this->entityHydrator->dehydrate($e)[$fieldName] ?? null;
                                    if($e instanceof Collection) $e = $e->toArray();

                                    return $e;

                                }, $targetValue), PHP_INT_MAX, ARRAY_FLATTEN_PRESERVE_KEYS);
                            }

                            // Check out database
                            $targetRepository = $this->entityManager->getRepository($targetName);
                            $targetFieldName = end($fieldPath);
                            $targetValue = array_values($targetValue);

                            if($this->classMetadataManipulator->hasAssociation($targetName, $targetFieldName))
                                $inDatabase |= ($targetRepository->findOneBy($targetValue) !== null);
                            else if($this->classMetadataManipulator->hasField($targetName, $targetFieldName))
                                $inDatabase |= ($targetRepository->findOneBy([$targetFieldName => $targetValue]) !== null);
                        }

                        if($inDatabase) $existingEntities[$spreadsheet][$baseName][] = $entity;
                        else if($isInvalid) $invalidEntities[$spreadsheet][$baseName][]   = $entity;
                        else {

                            $newEntities[$spreadsheet][$baseName][] = $entity;

                            if ($onFly) {
                                try {

                                    $this->entityManager->persist($entity);
                                    if ($counter && ($counter % $batch) == 0) {
                                        $this->entityManager->flush();
                                        $this->entityManager->clear();
                                    }

                                    $counter++;

                                } catch (\Exception $e) {
                                    $msg = " Failed to write ".$this->translator->entity($entity)."(".$entity.")  ";
                                    $output->writeln('');
                                    $output->writeln('');
                                    $output->writeln('<red,bkg>'.str_blankspace(strlen($msg)));
                                    $output->writeln($msg);
                                    $output->writeln(str_blankspace(strlen($msg)).'</red,bkg>');
            
                                    throw $e;
                                }
                            }
                        }
                    }
                }
            }

            //
            // Check if duplicates found in the entity data list
            foreach($entityUniqueValues as $targetName => $_) {
                foreach($_ as $uniqueKey => $values) {

                    $occurences = array_filter(array_count_values($values), fn($v) => $v > 1);
                    $duplicates = !empty($occurences);
                    if($duplicates)
                        throw new Exception("Duplicate entries \"".implode(",", array_unique(array_keys($occurences)))."\" found for $targetName::$".$uniqueKey);
                }
            }
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('');

        $newCount = 0;
        $output->writeln(' <info>New data found: </info>'.implode(", ", array_map(function($spreadsheet) use (&$newCount, $baseClass, $entityData, $newEntities) {
          
            $countData = 0;
            foreach(array_keys($entityData[$spreadsheet]) as $baseName)
                $countData    += count($entityData[$spreadsheet][$baseName] ?? []);

            $countNewData = 0;
            foreach(array_keys($newEntities[$spreadsheet]) as $baseName)
                $countNewData += count($newEntities[$spreadsheet][$baseName] ?? []);

            $newCount = $count = $countData > 0 ? $countNewData."/".$countData : "0";
            $plural       = ($countNewData > 1);

            return $count." <ln>".lcfirst($this->translator->entity($baseClass[$spreadsheet], $plural ? Translator::TRANSLATION_PLURAL : Translator::TRANSLATION_SINGULAR)) .'</ln>';

        }, array_keys(array_filter($entityData)))));

        if($show) {

            foreach($entityData as $spreadsheet => $___) {
                foreach($___ as $baseName => $__) {

                    $output->writeln("\n * <info>Spreadsheet \"".$spreadsheet."\"</info>: $baseName");
                    foreach($existingEntities[$spreadsheet][$baseName] ?? [] as $entry)
                        $output->writeln("\t<warning>".$this->translator->entity($entry).": </warning> \"". $entry."\" found in database");
                    foreach($invalidEntities[$spreadsheet][$baseName] ?? [] as $entry)
                        $output->writeln("\t<red>".$this->translator->entity($entry).": </red>\"". $entry."\"cannot be imported (parent already persisting..)");
                    foreach($newEntities[$spreadsheet][$baseName] ?? [] as $entry)
                        $output->writeln("\t<ln>".$this->translator->entity($entry).": </ln><info>\"". $entry."\"</info> ".($onFly ? " just got imported on the fly" : "is ready for import")." !");
                }
            }
        }

        $output->writeln("");

        if($newCount == 0 || empty(array_filter_recursive($newEntities))) {

            $msg = ' [OK] Nothing to update - your database is already in sync with the current dataset. ';
            $output->writeln('<info,bkg>'.str_blankspace(strlen($msg)));
            $output->writeln($msg);
            $output->writeln(str_blankspace(strlen($msg)).'</info,bkg>');

            $output->section()->writeln("");
            return Command::SUCCESS;
        }
        
        if(!$onFly) {

            if($force) $apply = "y";
            else {
            
                $helper   = $this->getHelper('question');
                $question = new Question(' > ');

                $output->writeln(' <info>Do you want to import these entries into the database ? (yes/no)</info> [<warning>no</warning>]: ');
                $apply = $helper->ask($input, $output, $question);

                if(strtolower($apply) != "y" && strtolower($apply) != "yes")
                    return Command::FAILURE;
            }

            $counter = 0;
            $totalNewEntries = count_leaves($newEntities);
            $progressBar = new ProgressBar($output, $totalNewEntries);
            foreach($newEntities as $spreadsheet => &$_) {

                foreach($_ as $baseName => $entries) {

                    foreach($entries as $entity) {

                        $progressBar->advance();
                        try {
                            
                            $this->entityManager->persist($entity);
                            if ($counter && ($counter % $batch) == 0)
                                $this->entityManager->flush();

                            $counter++;

                        } catch (\Exception $e) {

                            $msg = " Failed to write ".$this->translator->entity($entity)."(".$entity.")  ";
                            $output->writeln('');
                            $output->writeln('');
                            $output->writeln('<red,bkg>'.str_blankspace(strlen($msg)));
                            $output->writeln($msg);
                            $output->writeln(str_blankspace(strlen($msg)).'</red,bkg>');

                            throw $e;
                        }
                    }
                }

                $this->entityManager->flush();
            }
        
            $progressBar->finish();
        }

        $output->section()->writeln("\n\n Updating database schema...");
        $output->section()->writeln("\n\t <info>".$totalNewEntries."</info> entries were added\n");
        
        $msg = ' [OK] Database content imported successfully! ';
        $output->writeln('<info,bkg>'.str_blankspace(strlen($msg)));
        $output->writeln($msg);
        $output->writeln(str_blankspace(strlen($msg)).'</info,bkg>');

        $output->section()->writeln("");
        return Command::SUCCESS;

        $output->section()->writeln("");
        return Command::SUCCESS;
    }
}
