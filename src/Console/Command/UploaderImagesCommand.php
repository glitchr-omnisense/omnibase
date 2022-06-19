<?php

namespace Base\Console\Command;

use Base\Entity\Layout\Image;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'uploader:images', aliases:[], description:'')]
class UploaderImagesCommand extends UploaderEntitiesCommand
{
    protected function configure(): void
    {
        $this->addOption('crops', false, InputOption::VALUE_NONE, 'Do you want to consider cropped images as well ?');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityName ??= str_strip($input->getOption('entity'), "App\\Entity\\");
        $this->warmup     ??= $input->getOption('warmup');
        $this->crops      ??= $input->getOption('crops');

        $this->appEntities ??= "App\\Entity\\".$this->entityName;
        if(!$this->appEntities instanceof Image) $this->appEntities = null;

        $this->baseEntities ??= "Base\\Entity\\".$this->entityName;
        if(!$this->baseEntities instanceof Image) $this->baseEntities = null;

        $ret = parent::execute($input, $output);

        dump($this->warmup, $this->crops);

        return $ret;
    }
}
