<?php

namespace Base\Controller;

use Base\Component\HttpFoundation\Referrer;
use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class LocaleController extends AbstractController
{
    /**
     * @var LocaleProviderInterface
     */
    protected $localeProvider;

    public function __construct(LocaleProviderInterface $localeProvider, RouterInterface $router, Referrer $referrer)
    {
        $this->localeProvider = $localeProvider;
        $this->router         = $router;
        $this->referrer       = $referrer;
    }

    /**
     * @Route("/changeto/{locale}", name="locale_changeto")
     */
    public function ChangeTo(Request $request, Referrer $referrer, ?string $locale = null)
    {
        $referrer->setUrl($_SERVER["HTTP_REFERER"] ?? null);

        $locale = $locale ? $this->localeProvider->getLocale($locale) : null;
        if($locale === null)
            $request->getSession()->remove('_locale');
        else if (in_array($locale, $this->localeProvider->getAvailableLocales()))
            $request->getSession()->set('_locale', $locale);

        $lang = $locale ? ".".LocaleProvider::getLang($locale) : "";
        $referrerName = $this->router->getRouteName(strval($referrer));
        if($referrerName !== "locale_changeto") {

            try { return $this->redirect($this->router->generate($referrerName.$lang)); }
            catch (RouteNotFoundException $e) { return $this->redirect($this->router->generate($referrerName)); }
        }

        $baseDir = $request->getBasePath();
        if(!$baseDir) $baseDir = "/";

        return $this->redirect($baseDir);
    }
}
