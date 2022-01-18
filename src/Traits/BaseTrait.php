<?php

namespace Base\Traits;

use Base\Annotations\AnnotationReader;
use Base\Notifier\NotifierInterface;
use Base\Service\BaseService;
use Base\Service\BaseSettings;
use Base\Service\IconService;
use Base\Service\ImageService;
use Base\Service\LocaleProviderInterface;
use Base\Twig\Extension\BaseTwigExtension;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

trait BaseTrait
{
    public static function getAnnotationReader() : ?AnnotationReader     { return AnnotationReader::getInstance(); }

    public static function getEnvironment()   : string { return BaseService::$environment; }
    public static function getProjectDir()    : string { return BaseService::$projectDir; }
    public static function getPublicDir()     : string { return BaseService::getProjectDir() . "/public"; }
    public static function getTemplateDir()   : string { return BaseService::getProjectDir() . "/templates"; }
    public static function getTranslationDir(): string { return BaseService::getProjectDir() . "/translations"; }
    public static function getCacheDir()      : string { return BaseService::getProjectDir() . "/var/cache/" . BaseService::getEnvironment(); }
    public static function getLogDir()        : string { return BaseService::getProjectDir() . "/var/log"; }
    public static function getDataDir()       : string { return BaseService::getProjectDir() . "/data"; }

    public static function getService()       : ?BaseService             { return (self::class === BaseService::class) ? BaseService::$instance       : BaseService::getService(); }
    public static function getImageService()  : ?ImageService            { return (self::class === BaseService::class) ? BaseService::$imageService   : BaseService::getImageService(); }
    public static function getIconService()   : ?IconService             { return (self::class === BaseService::class) ? BaseService::$iconService    : BaseService::getIconService(); }
    public static function getLocaleProvider(): ?LocaleProviderInterface { return (self::class === BaseService::class) ? BaseService::$localeProvider : BaseService::getLocaleProvider(); }
    public static function getSettings()      : ?BaseSettings            { return (self::class === BaseService::class) ? BaseService::$settings       : BaseService::getSettings(); }
    public static function getRouter()        : ?RouterInterface         { return (self::class === BaseService::class) ? BaseService::$router         : BaseService::getRouter(); }
    public static function getTwigExtension() : ?BaseTwigExtension       { return (self::class === BaseService::class) ? BaseService::$twigExtension  : BaseService::getTwigExtension(); }
    public static function getTwig()          : ?Environment             { return (self::class === BaseService::class) ? BaseService::$twig           : BaseService::getTwig(); }
    public static function getNotifier()      : ?NotifierInterface       { return (self::class === BaseService::class) ? BaseService::$notifier       : BaseService::getNotifier(); }
    public static function getTranslator()    : ?TranslatorInterface     { return (self::class === BaseService::class) ? BaseService::$translator     : BaseService::getTranslator(); }
    public static function getSlugger()       : ?SluggerInterface        { return (self::class === BaseService::class) ? BaseService::$slugger        : BaseService::getSlugger(); }
}
