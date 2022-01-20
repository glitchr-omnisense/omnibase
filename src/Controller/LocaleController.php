<?php

namespace Base\Controller;

use Base\Component\HttpFoundation\Referrer;
use Base\Service\LocaleProviderInterface;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LocaleController extends AbstractController
{
    /**
     * @var LocaleProviderInterface
     */
    protected $localeProvider;

    public function __construct(LocaleProviderInterface $localeProvider)
    {
        $this->localeProvider = $localeProvider;
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

        return $this->redirect($referrer);
    }
}