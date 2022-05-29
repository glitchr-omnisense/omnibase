<?php

namespace Base\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Base\Console\Command;
use Base\Database\Factory\ClassMetadataManipulator;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'entity:discriminator', aliases:[], description:'')]
class EntityDiscriminatorCommand extends Command
{
    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('entity', InputArgument::REQUIRED, 'Which entity should be considered ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entity                  = $input->getArgument('entity');

        if(!$this->classMetadataManipulator->isEntity($entity))
            throw new Exception("Class \"".$entity."\" is not an entity.");

        dump($this->classMetadataManipulator->getClassMetadata($entity)->discriminatorMap);
        return Command::SUCCESS;
    }
}
