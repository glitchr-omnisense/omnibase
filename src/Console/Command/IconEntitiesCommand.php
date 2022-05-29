<?php

namespace Base\Console\Command;

use Base\BaseBundle;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Base\Console\Command;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Model\IconizeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'icon:entities', aliases:[], description:'')]
class IconEntitiesCommand extends Command
{
    public function __construct(EntityManagerInterface $entityManager, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id',   null, InputOption::VALUE_OPTIONAL, 'Should I pick up one specific entityId ?');
        $this->addOption('entity',   null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific entity ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseLocation = dirname((new \ReflectionClass('Base\\BaseBundle'))->getFileName());
        $entityId = $input->getOption('id');
        $entityClass = $input->getOption('entity');

        if($entityClass) {

            if(!$this->classMetadataManipulator->isEntity($entityClass))
                throw new \Exception("Entity \"$entityClass\" doesn't exists");
            if(!class_implements_interface($entityClass, IconizeInterface::class))
                throw new \Exception("Entity \"$entityClass\" doesn't implement ".IconizeInterface::class);

            $repository = $this->entityManager->getRepository($entityClass);
            $entities = $entityId ? $repository->findBy(["id" => $entityId]) : $repository->findAll();
            if(empty($entities)) {

                if($entityId) throw new \Exception($entityClass." #".$entityId." not found.");
                else throw new \Exception("No ".$entityClass." found.");
            }

            $maxLength = 0;
            foreach($entities as $entity)
                $maxLength = max(strlen($entity), $maxLength);

            foreach($entities as $entity) {

                $space = str_repeat(" ", max($maxLength-strlen($entity), 0));
                $icons = $entity->__iconize();
                $output->section()->writeln("<info>".$entityClass." #".$entity->getId()."</info>; <warning>$entity</warning> $space: [".implode(",", $icons ?? [])."]");
            }

        } else {

            $entities = array_filter(array_merge(
                BaseBundle::getAllClasses($baseLocation."/Entity"),
                BaseBundle::getAllClasses("./src/Entity"),
            ), fn($c) => class_implements_interface($c, IconizeInterface::class));

            if($entities) $output->section()->writeln("Entity list: ".$entityClass);
            foreach($entities as $entity) {

                $icons = $entity::__iconizeStatic();
                $output->section()->writeln(" * <info>".$entity."</info>: [".implode(",", $icons ?? [])."]");
            }
        }
        return Command::SUCCESS;
    }
}
