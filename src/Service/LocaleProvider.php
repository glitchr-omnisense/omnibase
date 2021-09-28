<?php

namespace Base\Service;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LocaleProvider implements LocaleProviderInterface
{
    /**
     * @var RequestStack
     */
    protected $requestStack = null;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag = null;

    /**
     * @var TranslatorInterface
     */
    protected $translator = null;
    
    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag, ?TranslatorInterface $translator) {
        $this->requestStack = $requestStack;
        $this->parameterBag = $parameterBag;
        $this->translator   = $translator;
    }

    protected string $locale;
    protected string $defaultLocale;
    protected array $fallbackLocales = [];
    
    public function getLocale(): ?string
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if (! $currentRequest instanceof Request) {
            return null;
        }


        $currentLocale = $currentRequest->getLocale();
        if ($currentLocale !== '') {
            return $currentLocale;
        }

        if ($this->translator !== null) {
            return $this->translator->getLocale();
        }

        return null;
    }

    public function getDefaultLocale(): ?string
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest !== null) {
            return $currentRequest->getDefaultLocale();
        }

        return null;
    }
    
    public function getFallbackLocales(): array
    {
        $fallbackLocales = $this->translator->getFallbackLocales();
        if(!empty($fallbackLocales)) return $fallbackLocales;

        return [$this->getDefaultLocale()];
    }
}