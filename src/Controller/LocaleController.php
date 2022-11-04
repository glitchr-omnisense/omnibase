<?php

namespace Base\Controller;

use Base\Service\ReferrerInterface;
use Base\Service\LocaleProvider;
use Base\Service\LocaleProviderInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    public function __construct(LocaleProviderInterface $localeProvider, EntityManagerInterface $entityManager, RouterInterface $router, ReferrerInterface $referrer, TranslatorInterface $translator)
    {
        $this->localeProvider = $localeProvider;
        $this->router         = $router;
        $this->referrer       = $referrer;
        $this->translator     = $translator;
        $this->entityManager  = $entityManager;
    }

    /**
     * @Route("/change/to/{_locale}", name="locale_changeto")
     */
    public function ChangeTo(Request $request, ReferrerInterface $referrer, ?string $_locale = null)
    {
        if($_locale === null)
            setcookie('_locale', null);

        $referrer->setUrl($_SERVER["HTTP_REFERER"] ?? null);
        $referrerName = $this->router->getRouteName(strval($referrer));
        $referrerParameters = array_filter($this->router->match(strval($referrer)), fn($a) => !str_starts_with($a, "_"), ARRAY_FILTER_USE_KEY);
        $referrer->setUrl(null);

        $availableLocales = array_merge($this->localeProvider->getAvailableLangs(), $this->localeProvider->getAvailableLocales());
        if(in_array($_locale, $availableLocales)) {

            setcookie('_locale', $_locale);
            if(!$this->isGranted("IS_IMPERSONATOR")){
                $this->getUser()?->setLocale($this->localeProvider->getLocale($_locale));
                $this->entityManager->flush();
            }

            $this->addFlash("info", $this->translator->trans("@controllers.locale_changeto.action", [$this->localeProvider->getLangName($_locale)]));

            if(!str_starts_with($referrerName, "locale_changeto")) {

                $referrer->setUrl(null);
                $lang = $_locale ? ".".$this->localeProvider->getLang($_locale) : "";

                try { return $this->redirect($this->router->generate($referrerName.$lang, $referrerParameters)); }
                catch (RouteNotFoundException $e) { return $this->redirect($this->router->generate($referrerName, $referrerParameters)); }
            }
        }

        return $this->redirect($this->router->getUrl($this->router->getIndexPage()));
    }
}
