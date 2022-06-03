<?php

namespace Base\Traits;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\SettingBag;
use Base\Service\IconProvider;
use Base\Service\ImageService;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Twig\Environment;

use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Symfony\Component\Notifier\NotifierInterface;

trait BaseCommonTrait {

    /**
     * @var BaseService
     */
    protected static $instance = null;
    public function hasInstance() { return !self::$instance; }
    public function getInstance() { return  self::$instance; }
    public function setInstance($instance) { self::$instance = $instance; }

    /**
     * @var string
     */
    protected static $projectDir = null;
    public static function setProjectDir($projectDir) { self::$projectDir = $projectDir; }

    /**
     * @var string
     */
    protected static $environment = null;
    public static function setEnvironment(?string $environment) { self::$environment = $environment; }

    /**
     * @var TranslatorInterface
     */
    protected static $translator = null;

    public static function setTranslator(?TranslatorInterface $translator) {
        self::$translator = $translator;
    }

    /**
     * @var LocaleProviderInterface
     */
    protected static $localeProvider = null;
    public static function setLocaleProvider(?LocaleProviderInterface $localeProvider) {  self::$localeProvider = $localeProvider; }

    /**
     * @var SluggerInterface
     */
    protected static $slugger = null;
    public static function setSlugger(?SluggerInterface $slugger) {  self::$slugger = $slugger; }

    /**
     * @var IconProviderInterface
     */
    protected static $iconProvider = null;
    public static function setIconProvider(?IconProvider $iconProvider) {  self::$iconProvider = $iconProvider; }

    /**
     * @var ClassMetadataManipulator
     */
    protected static $classMetadataManipulator = null;
    public static function setClassMetadataManipulator(?ClassMetadataManipulator $classMetadataManipulator) {  self::$classMetadataManipulator = $classMetadataManipulator; }

    /**
     * @var ImageServiceInterface
     */
    protected static $imageService = null;
    public static function setImageService(?ImageService $imageService) {  self::$imageService = $imageService; }

    /**
     * @var AdvancedRouterInterface
     */
    protected static $router = null;
    public static function setRouter(AdvancedRouterInterface $router) { self::$router = $router; }

    /**
     * @var Environment
     */
    protected static $twig;
    public static function setTwig(Environment $twig) { self::$twig = $twig; }

    /**
     * @var SettingBag
     */
    protected static $settings;
    public static function setSettingBag(SettingBagInterface $settings) { self::$settings = $settings; }

    /**
     * @var ParameterBag
     */
    protected static $parameterBag;
    public static function setParameterBag(ParameterBagInterface $parameterBag) { self::$parameterBag = $parameterBag; }

    /**
     * @var NotifierInterface
     */
    protected static $notifier = null;
    public static function setNotifier(NotifierInterface $notifier) { self::$notifier = $notifier; }
}
