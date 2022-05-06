<?php

namespace Base\Controller;

use Base\Component\HttpFoundation\Referrer;
use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class LocaleController extends AbstractController
{
    /**
     * @var LocaleProviderInterface
     */
    protected $localeProvider;

    public function __construct(LocaleProviderInterface $localeProvider, RouterInterface $router)
    {
        $this->localeProvider = $localeProvider;
        $this->router         = $router;
    }

    /**
     * @Route("/changeto/{locale}", name="locale_changeto")
     */
    public function ChangeTo(Request $request, Referrer $referrer, ?string $locale = null)
    {
        $locale = $locale ? $this->localeProvider->getLocale($locale) : null;
        if($locale === null) 
            $request->getSession()->remove('_locale');
        else if (in_array($locale, $this->localeProvider->getAvailableLocales()))
            $request->getSession()->set('_locale', $locale);

        $lang = $locale ? ".".LocaleProvider::getLang($locale) : "";
        $referrerName = $this->router->getRouteName(strval($referrer));
        $referrerUrl  = $this->router->getUrl($referrerName.$lang) ?? $this->router->getUrl($referrerName);

        return $this->redirect($referrerUrl ? $referrerUrl : $request->getBasePath());
    }
}