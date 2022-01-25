<?php

namespace Base\Command;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\Annotation\Iconize;
use Base\Annotations\AnnotationReader;
use Base\BaseBundle;
use Base\Component\Console\Command\Command;
use Base\Model\IconizeInterface;
use Base\Service\BaseService;
use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;
use Base\Service\TranslatorInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class TranslationControllersCommand extends Command
{
    protected static $defaultName = 'translation:controllers';

    public function __construct(TranslatorInterface $translator, LocaleProviderInterface $localeProvider, RouterInterface $router, BaseService $baseService)
    {
        $this->translator = $translator;
        $this->localeProvider = $localeProvider;
        $this->router = $router;
        $this->baseService = $baseService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('controller', null, InputOption::VALUE_OPTIONAL, 'Should I consider only a specific controller ?');
        $this->addOption('suffix',     null, InputOption::VALUE_OPTIONAL, 'Should I add a specific translation suffix to the default path ?', "title");
        $this->addOption('locale',     null, InputOption::VALUE_OPTIONAL, 'Should I display only a specific locale ?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $controllers = array_transforms(function($k, $v):?array {

            if(str_starts_with($v->getDefault("_controller"), "App"))  return [$k, $v->getDefault("_controller")];
            if(str_starts_with($v->getDefault("_controller"), "Base")) return [$k, $v->getDefault("_controller")];
            return null;

        }, $this->router->getRouteCollection()->all());

        $controllerRestriction = $input->getOption('controller') ?? "";

        $maxLength = 0;
        if(!$controllerRestriction) {
            foreach($controllers as $controller)
               $maxLength = max(strlen($controller), $maxLength);
        }

        $locale = $input->getOption('locale');
        $locale = $locale ? $this->localeProvider->getLocale($locale) : null;
        $availableLocales = LocaleProvider::getAvailableLocales();
        if($locale && !in_array($locale, $availableLocales))
            throw new \Exception("Locale not found in the list of available locale: [".implode(",", $availableLocales)."]");

        $suffix = $input->getOption('suffix');
        if($controllers) $output->section()->writeln("Controller list:");
        foreach($controllers as $path => $controller) {

            if(!str_starts_with($controller, $controllerRestriction)) continue;

            $trans = "";
            foreach($availableLocales as $currentLocale) {

                if($locale !== null && $locale != $currentLocale) continue;
                if($locale === null) {
                    $prefix = "\n\t - ";
                    $space = "";
                } else {
                    $prefix = "";
                    $space = str_repeat(" ", max($maxLength-strlen($controller), 0));
                }

                $translationPath = "@controllers.".$path.".".$suffix;
                $translationPathStr = $prefix."@controllers[$currentLocale].<ln>".$path.".".$suffix."</ln>";
                $translation = $this->translator->trans($translationPath, [], null, $currentLocale);

                if($translation == $translationPath) $trans .= "<warning>".$translationPathStr."</warning><red> = \"no translation found\"</red>";
                else $trans .= "<warning>".$translationPathStr." </warning>= \"". $translation."\"";
            }

            $output->section()->writeln(" * <info>".trim($controller)."</info> ".$space.": $trans");
        }

        return Command::SUCCESS;
    }
}
