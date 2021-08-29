<?php

namespace Base\Service\Traits;

use Base\Service\BaseService;
use Base\Twig\BaseTwigExtension;
use Symfony\Component\Config\Definition\Exception\Exception;
use Twig\Environment;

use Base\Traits\BaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

trait BaseCommonTrait {

    use BaseTrait;
    
    /**
     * @var string
     */
    public static $projectDir = null;
    public static function setProjectDir($projectDir) { return self::$projectDir = $projectDir; }

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
     * @var SluggerInterface
     */
    public static $slugger = null;
    public static function setSlugger(?SluggerInterface $slugger) {  self::$slugger = $slugger; }

    /**
     * @var NotifierInterface
     */
    public static $notifier = null;

    /**
     * @var ChannelPolicyInterface
     */
    public static $notifierPolicy = [];

    /**
     * @var EntityManagerInterface
     */
    public static $entityManager;
    public static function setEntityManager(EntityManagerInterface $entityManager) { self::$entityManager = $entityManager; }

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

}