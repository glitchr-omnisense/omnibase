<?php

namespace Base\Traits;

use Base\Service\BaseService;
use Base\Service\LocaleProviderInterface;
use Base\Twig\Extension\BaseTwigExtension;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

trait BaseTrait
{
    public static function getEnvironment(): string { return BaseService::$environment; }
    public static function getProjectDir(): string { return BaseService::$projectDir; }
    public static function getPublicDir(): string { return BaseService::getProjectDir() . "/public"; }
    public static function getTemplateDir(): string { return BaseService::getProjectDir() . "/templates"; }
    public static function getTranslationDir(): string { return BaseService::getProjectDir() . "/translations"; }
    public static function getCacheDir(): string { return BaseService::getProjectDir() . "/var/cache/".BaseService::getEnvironment(); }
    public static function getLogDir(): string { return BaseService::getProjectDir() . "/var/log"; }
    public static function getDataDir(): string { return BaseService::getProjectDir() . "/data"; }

    public static function getRouter(): ?RouterInterface { return BaseService::$router; }
    public static function getLocaleProvider(): ?LocaleProviderInterface { return BaseService::$localeProvider; }
    public static function getTwigExtension(): ?BaseTwigExtension { return BaseService::$twigExtension; }
    public static function getTwig(): ?Environment { return BaseService::$twig; }
    public static function getDoctrine() { return BaseService::$doctrine; }
    
    public static function getNotifier(): ?NotifierInterface { return BaseService::$notifier; }
    public static function getNotifierPolicy(): ?ChannelPolicyInterface { return BaseService::$notifierPolicy; }

    public static function getRepository(): ?EntityRepository { return BaseService::$doctrine->getRepository(get_called_class()); }
    public static function getTranslator(): ?TranslatorInterface { return BaseService::$translator; }
    public static function getSlugger(): ?SluggerInterface { return BaseService::$slugger; }
}
