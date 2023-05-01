<?php

namespace Base\Twig;

use Base\Service\LocalizerInterface;
use Base\Service\ReferrerInterface;
use Base\Service\Localizer;
use Base\Service\SettingBag;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Base\Traits\ProxyTrait;
use Base\Twig\Variable\BackofficeVariable;
use Base\Twig\Variable\EasyAdminVariable;
use Base\Twig\Variable\EmailVariable;
use Base\Twig\Variable\RandomVariable;
use Base\Twig\Variable\SiteVariable;
use Twig\Environment;

class AppVariable
{
    use ProxyTrait;

    protected array $meta;

    /**
     * @var ReferrerInterface
     */
    public ReferrerInterface $referrer;

    /**
     * @var Environment
     */
    public Environment $twig;

    /**
     * @var SettingBagInterface
     */
    public SettingBagInterface $settingBag;

    /**
     * @var ParameterBagInterface
     */
    public ParameterBagInterface $parameterBag;

    /**
     * @var EasyAdminVariable
     */
    public EasyAdminVariable $ea;

    /**
     * @var SiteVariable
     */
    public SiteVariable $site;

    /**
     * @var RandomVariable
     */
    public RandomVariable $random;

    /**
     * @var EmailVariable
     */
    public EmailVariable $email;

    /**
     * @var BackofficeVariable
     */
    public BackofficeVariable $backoffice;

    /**
     * @var LocalizerInterface
     */
    public LocalizerInterface $localizer;

    public function __construct(
        \Symfony\Bridge\Twig\AppVariable $appVariable,
        EasyAdminVariable                $ea,
        RandomVariable                   $random,
        SiteVariable                     $site,
        EmailVariable                    $email,
        BackofficeVariable               $backoffice,
        SettingBag                       $settingBag,
        ParameterBagInterface            $parameterBag,
        ReferrerInterface                $referrer,
        Environment                      $twig,
        Localizer                        $localizer
    )
    {
        $this->settingBag = $settingBag;
        $this->referrer = $referrer;
        $this->twig = $twig;
        $this->parameterBag = $parameterBag;
        $this->localizer = $localizer;

        $this->backoffice = $backoffice;
        $this->random = $random;
        $this->site = $site;
        $this->email = $email;
        $this->ea = $ea;

        $this->setProxy($appVariable);
    }

    public function getGlobals()
    {
        return array_transforms(
            fn($k, $v): ?array => $k != "app" && str_starts_with($k, "app") ? [str_strip($k, "app."), $v] : null,
            $this->twig->getGlobals()
        );
    }

    public function bag(?string $key = null, ?array $bag = null)
    {
        return $key ? $this->parameterBag->get($key, $bag) ?? null : $this->parameterBag;
    }

    public function settings()
    {
        return $this->settingBag->get("app.settings") ?? [];
    }

    public function referrer()
    {
        return $this->referrer;
    }

    public function locale()
    {
        return [
            "_self" => $this->localizer->getLocale(),
            "lang" => $this->localizer->getLocaleLang(),
            "country" => $this->localizer->getLocaleCountry()
        ];
    }
}
