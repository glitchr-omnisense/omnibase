<?php

namespace Base\Console\Command;

use Base\Annotations\Annotation\Iconize;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Service\BaseService;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController as EaCrudController;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IconCrudCommand extends Command
{
    protected static $defaultName = 'icon:crud';

    protected function configure(): void
    {
        $this->addOption('crud',   null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific CRUD controller ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseLocation = dirname((new \ReflectionClass('Base\\BaseBundle'))->getFileName());
        $crudRestriction = $input->getOption('crud') ?? "";
        $cruds = array_filter(
            array_merge(
                BaseBundle::getAllClasses($baseLocation."/Controller/Dashboard/Crud"),
                BaseBundle::getAllClasses("./src/Controller/Dashboard/Crud"), 
            ), fn($c) => !($c instanceof EaCrudController)
        );

        if($cruds) $output->section()->writeln("CRUD controller list: ".$crudRestriction);
        foreach($cruds as $crud) {
        
            if(!str_starts_with($crud, $crudRestriction)) continue;

            $icon = $crud instanceof AbstractCrudController ? $crud::getPreferredIcon() : null;
            $iconize = $icon ? "<warning>(implements ".Iconize::class.")</warning>: \"$icon\"" : "<red>(no icon found)</red>";
            $output->section()->writeln(" * <info>".trim($crud)."</info> ".$iconize);
        }

        return Command::SUCCESS;
    }
}
