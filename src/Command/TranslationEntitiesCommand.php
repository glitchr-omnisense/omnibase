<?php

namespace Base\Command;

use Base\BaseBundle;
use Base\Component\Console\Command\Command;
use Base\Service\BaseService;
use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;
use Base\Service\TranslatorInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationEntitiesCommand extends Command
{
    protected static $defaultName = 'translation:entities';

    public function __construct(TranslatorInterface $translator, LocaleProviderInterface $localeProvider, BaseService $baseService)
    {
        $this->translator = $translator;
        $this->localeProvider = $localeProvider;

        $this->baseService = $baseService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific entity ?');
        $this->addOption('suffix', null, InputOption::VALUE_OPTIONAL, 'Should I add a specific translation suffix to the default path ?', "singular");
        $this->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Should I display only a specific locale ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseLocation = dirname((new \ReflectionClass('Base\\BaseBundle'))->getFileName());
        $entityRestriction = $input->getOption('entity') ?? "";
        $entities = array_merge(
            BaseBundle::getAllNamespacesAndClasses("./src/Entity"),
            BaseBundle::getAllNamespacesAndClasses($baseLocation."/Entity"), 
        );
        $namespaces = array_merge(
            BaseBundle::getAllNamespaces("./src/Entity"),
            BaseBundle::getAllNamespaces($baseLocation."/Entity"), 
        );
        
        $maxLength = [];
        if(!$entityRestriction) {
            foreach($entities as $entity) {
                if(in_array($entity, ["App\Entity","Base\Entity"])) continue;
                if(str_ends_with($entity, "Translation")) continue;

                $maxLength[class_namespace($entity)] = max(strlen($entity), $maxLength[class_namespace($entity)] ?? 0);
            }
        }

        $locale = $input->getOption('locale');
        $locale = $locale ? $this->localeProvider->getLocale($locale) : null;
        $availableLocales = LocaleProvider::getAvailableLocales();
        if($locale && !in_array($locale, $availableLocales))
            throw new \Exception("Locale not found in the list of available locale: [".implode(",", $availableLocales)."]");

        $suffix = $input->getOption('suffix');
        if($entities) $output->section()->writeln("Entity list: ".$entityRestriction);
        foreach($entities as $entity) {

            if($entity == "App\Entity") continue;
            if($entity == "Base\Entity") continue;
            if(str_ends_with($entity, "Translation")) continue;
            if(!str_starts_with($entity, $entityRestriction)) continue;

            $isClass = class_exists($entity) ? " <info>& class</info>" : "";
            $entityStr = $entity . (in_array($entity, $namespaces) ? " (is namespace".$isClass.")" : null);
            $color = in_array($entity, $namespaces) ? "magenta" : "info";

            $trans = "";
            foreach($availableLocales as $currentLocale) {

                if($locale !== null && $locale != $currentLocale) continue;
                $prefix = "\n\t - ";
                $space = "";
            
                $path = explode("\\", $entity);
                $path = implode(".", tail($path, 2));
                $translationPath = "@entities.".camel_to_snake($path, ".").".".$suffix;
                $translationPathStr = $prefix."@entities[$currentLocale].<ln>".camel_to_snake($path).".".$suffix."</ln>";
                $translation = $this->translator->trans($translationPath, [], null, $currentLocale);

                if($translation == $translationPath) $trans .= "<warning>".$translationPathStr."</warning><red> = \"no translation found\"</red>";
                else $trans .= "<warning>".$translationPathStr." </warning>= \"". $translation."\"";
            }

            $carriageReturn = in_array($entity, $namespaces) ? "\n" : "";
            $output->section()->writeln($carriageReturn." * <".$color.">".trim($entityStr)."</".$color."> ".$space.": $trans");
        }


        return Command::SUCCESS;
    }
}