<?php

namespace Base\Traits;

use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Service\Obfuscator;
use Base\Service\TradingMarketInterface;
use Doctrine\ORM\EntityManagerInterface;
use Base\Routing\RouterInterface;
use Base\Service\SettingBag;
use Base\Service\IconProvider;
use Base\Service\ImageServiceInterface;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Twig\Environment;
use Base\Twig\Extension\BaseTwigExtension;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;

trait BaseCommonTrait {

    /**
     * @var BaseService
     */
    protected static $instance = null;
    public function hasInstance() { return self::$instance !== null; }
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
     * @var RequestStack
     */
    protected static $requestStack = null;
    public static function setRequestStack(RequestStack $requestStack) {
        self::$requestStack = $requestStack;
    }

    /**
     * @var Obfuscator
     */
    protected static $obfuscator = null;
    public static function setObfuscator(Obfuscator $obfuscator) {
        self::$obfuscator = $obfuscator;
    }

    /**
     * @var EntityManagerInterface
     */
    protected static $entityManager = null;
    public static function setEntityManager(EntityManagerInterface $entityManager) {
        self::$entityManager = $entityManager;
    }

    /**
     * @var ManagerRegistry
     */
    protected static $doctrine = null;
    public static function setDoctrine(ManagerRegistry $doctrine) {
        self::$doctrine = $doctrine;
    }

    /**
     * @var EntityHydratorInterface
     */
    protected static $entityHydrator = null;
    public static function setEntityHydrator(EntityHydratorInterface $entityHydrator) {
        self::$entityHydrator = $entityHydrator;
    }

    /**
     * @var LocalizerInterface
     */
    protected static $localizer = null;
    public static function setLocalizer(?LocalizerInterface $localizer) {  self::$localizer = $localizer; }

    /**
     * @var TradingMarketInterface
     */
    protected static $tradingMarket = null;
    public static function setTradingMarket(?TradingMarketInterface $tradingMarket) {  self::$tradingMarket = $tradingMarket; }

    /**
     * @var LocalizerInterface
     */
    protected static $tokenStorage = null;
    public static function setTokenStorage(?TokenStorageInterface $tokenStorage) {  self::$tokenStorage = $tokenStorage; }

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
    public static function setImageService(?ImageServiceInterface $imageService) {  self::$imageService = $imageService; }

    /**
     * @var FirewallMapInterface
     */
    protected static $firewallMap = null;
    public static function setFirewallMap(?FirewallMapInterface $firewallMap) {  self::$firewallMap = $firewallMap; }

    /**
     * @var RouterInterface
     */
    protected static $router = null;
    public static function setRouter(RouterInterface $router) { self::$router = $router; }

    /**
     * @var Environment
     */
    protected static $twig;
    public static function setTwig(Environment $twig) { self::$twig = $twig; }

    /**
     * @var BaseTwigExtension
     */
    protected static $twigExtension = null;
    public static function setTwigExtension(BaseTwigExtension $twigExtension) {  self::$twigExtension = $twigExtension; }

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
