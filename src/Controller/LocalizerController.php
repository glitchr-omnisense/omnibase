<?php

namespace Base\Controller;

use Base\Routing\RouterInterface;
use Base\Service\ReferrerInterface;
use Base\Service\LocalizerInterface;
use Base\Service\TranslatorInterface;
use Base\Subscriber\LocalizerSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 *
 */
class LocalizerController extends AbstractController
{
    /**
     * @var LocalizerInterface
     */
    protected LocalizerInterface $localizer;

    protected RouterInterface $router;
    protected ReferrerInterface $referrer;
    protected TranslatorInterface $translator;
    protected EntityManagerInterface $entityManager;

    public function __construct(LocalizerInterface $localizer, EntityManagerInterface $entityManager, RouterInterface $router, ReferrerInterface $referrer, TranslatorInterface $translator)
    {
        $this->localizer = $localizer;
        $this->router = $router;
        $this->referrer = $referrer;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/locale/{_locale}", name="_switch_locale")
     */
    public function Locale(Request $request, ReferrerInterface $referrer, ?string $_locale = null)
    {
        if ($_locale === null) {
            setcookie(LocalizerSubscriber::__LANG_IDENTIFIER__, null, "/", "." . $this->router->getDomain());
        }

        $referrer->setUrl($_SERVER["HTTP_REFERER"] ?? null);
        $referrerName = $this->router->getRouteName(strval($referrer));
        $referrerParameters = array_filter($this->router->match(strval($referrer)), fn($a) => !str_starts_with($a, "_"), ARRAY_FILTER_USE_KEY);
        $referrer->setUrl(null);

        $availableLocales = array_merge($this->localizer->getAvailableLocaleLangs(), $this->localizer->getAvailableLocales());
        if (in_array($_locale, $availableLocales)) {
            setcookie('_locale', $_locale);
            if (!$this->isGranted("IS_IMPERSONATOR")) {
                $this->getUser()?->setLocale($this->localizer->getLocale($_locale));
                $this->entityManager->flush();
            }

            $this->addFlash("info", $this->translator->trans("@controllers.locale_changeto.action", [$this->localizer->getLocaleLangName($_locale)]));

            if (!str_starts_with($referrerName, "_switch_locale")) {
                $referrer->setUrl(null);
                $lang = $_locale ? "." . $this->localizer->getLocaleLang($_locale) : "";

                try {
                    return $this->redirect($this->router->generate($referrerName . $lang, $referrerParameters));
                } catch (RouteNotFoundException $e) {
                    return $this->redirect($this->router->generate($referrerName, $referrerParameters));
                }
            }
        }

        return $this->redirect($this->router->getUrlIndex());
    }

    /**
     * @Route("/timezone/{_timezone}", name="_switch_timezone")
     */
    public function Timezone(Request $request, ReferrerInterface $referrer, ?string $_locale = null)
    {
        if ($_locale === null) {
            setcookie(LocalizerSubscriber::__LANG_IDENTIFIER__, null);
        }

        $referrer->setUrl($_SERVER["HTTP_REFERER"] ?? null);
        $referrerName = $this->router->getRouteName(strval($referrer));
        $referrerParameters = array_filter($this->router->match(strval($referrer)), fn($a) => !str_starts_with($a, "_"), ARRAY_FILTER_USE_KEY);
        $referrer->setUrl(null);

        $availableLocales = array_merge($this->localizer->getAvailableLocaleLangs(), $this->localizer->getAvailableLocales());
        if (in_array($_locale, $availableLocales)) {
            setcookie('_locale', $_locale);
            if (!$this->isGranted("IS_IMPERSONATOR")) {
                $this->getUser()?->setLocale($this->localizer->getLocale($_locale));
                $this->entityManager->flush();
            }

            $this->addFlash("info", $this->translator->trans("@controllers.locale_changeto.action", [$this->localizer->getLocaleLangName($_locale)]));

            if (!str_starts_with($referrerName, "_switch_timezone")) {
                $referrer->setUrl(null);
                $lang = $_locale ? "." . $this->localizer->getLocaleLang($_locale) : "";

                try {
                    return $this->redirect($this->router->generate($referrerName . $lang, $referrerParameters));
                } catch (RouteNotFoundException $e) {
                    return $this->redirect($this->router->generate($referrerName, $referrerParameters));
                }
            }
        }

        return $this->redirect($this->router->getUrlIndex());
    }
}
