<?php

namespace Base\Service\Traits;

use Base\Service\BaseService;
use Base\Service\BaseSettings;
use Base\Service\LocaleProviderInterface;
use Base\Twig\Extension\BaseTwigExtension;
use Symfony\Component\Config\Definition\Exception\Exception;
use Twig\Environment;

use Base\Traits\BaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Doctrine\Persistence\ManagerRegistry;

trait BaseCommonTrait {

    /**
     * @var string
     */
    public static $projectDir = null;
    public static function setProjectDir($projectDir) { return self::$projectDir = $projectDir; }
    public static $environment = null;
    public static function setEnvironment($environment) { return self::$environment = $environment; }

    /**
     * @var TranslatorInterface
     */
    public static $translator = null;

    /**
     * @var BaseTwigExtension
     */
    public static $twigExtension = null;

    public static function setTranslator(?TranslatorInterface $translator) {
        self::$translator = $translator;
        self::$twigExtension = new BaseTwigExtension($translator);
    }

    /**
     * @var LocaleProviderInterface
     */
    public static $localeProvider = null;
    public static function setLocaleProvider(?LocaleProviderInterface $localeProvider) {  self::$localeProvider = $localeProvider; }

    /**
     * @var SluggerInterface
     */
    public static $slugger = null;
    public static function setSlugger(?SluggerInterface $slugger) {  self::$slugger = $slugger; }

    /**
     * @var DoctrineInterface
     */
    public static $doctrine;
    public static function setDoctrine(ManagerRegistry $doctrine) { self::$doctrine = $doctrine; }

    /**
     * @var RouterInterface
     */
    public static $router = null;
    public static function setRouter(RouterInterface $router) { self::$router = $router; }
    
    /**
     * @var Environment
     */
    public static $twig;
    public static function setTwig(Environment $twig) { self::$twig = $twig; }

    /**
     * @var BaseSettings
     */
    
    public static $settings;
    public static function setSettings(BaseSettings $settings) { self::$settings = $settings; }
}
