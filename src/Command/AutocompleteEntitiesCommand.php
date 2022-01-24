<?php

namespace Base\Command;

use Base\Annotations\AnnotationReader;
use Base\BaseBundle;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Base\Component\Console\Command\Command;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Model\AutocompleteInterface;
use Base\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputOption;

class AutocompleteEntitiesCommand extends Command
{
    protected static $defaultName = 'autocomplete:entities';

    public function __construct(EntityManagerInterface $entityManager, ClassMetadataManipulator $classMetadataManipulator, BaseService $baseService)
    {
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->baseService = $baseService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id',   null, InputOption::VALUE_OPTIONAL, 'Which entity should I pick up ?');
        $this->addOption('entity',   null, InputOption::VALUE_OPTIONAL, 'Which class should I pick up?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseLocation = dirname((new \ReflectionClass('Base\\BaseBundle'))->getFileName());
        
        $entityId = $input->getOption('id') ?? null;
        $entityClass = $input->getOption('entity') ?? "";
        
        if($entityClass) {
        
            if(!$this->classMetadataManipulator->isEntity($entityClass))
                throw new \Exception("Entity \"$entityClass\" doesn't exists");
            if(!class_implements_interface($entityClass, AutocompleteInterface::class))
                throw new \Exception("Entity \"$entityClass\" doesn't implement ".AutocompleteInterface::class);

            $repository = $this->entityManager->getRepository($entityClass);
            $entities = $entityId ? $repository->findBy(["id" => $entityId]) : $repository->findAll();
            if(empty($entities)) {

                if($entityId) throw new \Exception($entityClass." #".$entityId." not found.");
                else throw new \Exception("No ".$entityClass." found.");
            }

            $maxLength = 0;
            foreach($entities as $entity) {
            
                $autocomplete = $entity->__autocomplete();
                $maxLength = max(strlen($autocomplete), $maxLength);
            }

            foreach($entities as $entity) {
                            
                $autocomplete = $entity->__autocomplete();
                $autocompleteData = $entity->__autocompleteData();
                if ($autocomplete)
                    $autocompleteData = trim(str_replace(["\t", "\n"], ["", " "], print_r($autocompleteData, true)));
    
                $space = str_repeat(" ", max($maxLength-strlen($autocomplete), 0));

                $output->section()->writeln("<info>".$entityClass." #".$entity->getId()."</info>; <warning>Autocomplete = </warning>\"". $autocomplete."\"".$space."<warning> / Data = </warning>\"$autocompleteData\"");
            }

        } else {

            $entity = array_filter(array_merge(
                BaseBundle::getAllClasses($baseLocation."/Entity"),
                BaseBundle::getAllClasses("./src/Entity"), 
            ), fn($c) => class_implements_interface($c, AutocompleteInterface::class));
    
            if($entity) $output->section()->writeln("Entity candidate list: ".$entityClass);
            foreach($entity as $entity)
                $output->section()->writeln(" * <info>".$entity."</info>");
        }

        return Command::SUCCESS;
    }
}
