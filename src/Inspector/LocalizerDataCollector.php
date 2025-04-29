<?php

namespace Base\Inspector;

use Base\Service\LocalizerInterface;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 */
class LocalizerDataCollector extends AbstractDataCollector
{
    /** @var LocalizerInterface */
    private LocalizerInterface $localizer;

    public function __construct(LocalizerInterface $localizer)
    {
        $this->localizer = $localizer;
    }

    public function getName(): string
    {
        return 'locale';
    }

    public static function getTemplate(): ?string
    {
        return '@Base/inspector/localizer_data_collector.html.twig';
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getLocale()
    {
        return $this->localizer->getLocale();
    }

    public function collect(Request $request, Response $response, $exception = null): void
    {       
        $this->data["locales"] = $this->localizer->getAvailableLocales();

        $this->data["locale"] = $this->localizer->getLocale();
        $this->data["country"] = $this->localizer->getLocaleCountry();
        $this->data["lang"] = $this->localizer->getLocaleLang();

        $this->data["timezone"] = $this->localizer->getTimezone();

    }
}
