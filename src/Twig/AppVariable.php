<?php

namespace Base\Twig;

use Base\Service\ReferrerInterface;
use Base\Service\LocaleProvider;
use Base\Service\SettingBag;
use Base\Service\ParameterBagInterface;
use Base\Traits\ProxyTrait;
use Base\Twig\Variable\BackofficeVariable;
use Base\Twig\Variable\EasyAdminVariable;
use Base\Twig\Variable\RandomVariable;
use Base\Twig\Variable\SiteVariable;
use Twig\Environment;

class AppVariable
{
    use ProxyTrait;

    protected array $meta;

    /**
     * @var Referrer
     */
    protected $referrer;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var SettingBag
     */
    protected $settingBag;
    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    public function __construct(
        \Symfony\Bridge\Twig\AppVariable $appVariable, EasyAdminVariable $ea, RandomVariable $random, SiteVariable $site, BackofficeVariable $backoffice,
        SettingBag $settingBag, ParameterBagInterface $parameterBag,
        ReferrerInterface $referrer, Environment $twig, LocaleProvider $localeProvider)
    {
        $this->settingBag     = $settingBag;
        $this->referrer       = $referrer;
        $this->twig           = $twig;
        $this->parameterBag   = $parameterBag;
        $this->localeProvider = $localeProvider;

        $this->backoffice = $backoffice;
        $this->random     = $random;
        $this->site       = $site;
        $this->ea         = $ea;

        $this->setProxy($appVariable);
    }

    public function getGlobals() {

        return array_transforms(
            fn($k,$v):?array => $k != "app" && str_starts_with($k, "app") ? [str_strip($k, "app."), $v] : null,
            $this->twig->getGlobals()
        );
    }

    public function bag(?string $key = null, ?array $bag = null) { return $key ? $this->parameterBag->get($key, $bag) ?? null : $this->parameterBag; }
    public function settings() { return $this->settingBag->get("app.settings") ?? []; }
    public function referrer() { return $this->referrer; }

    public function locale()  {
        return [
            "_self"  => $this->localeProvider->getLocale(),
            "lang"    => $this->localeProvider->getLang(),
            "country" => $this->localeProvider->getCountry()
        ];
    }
}
