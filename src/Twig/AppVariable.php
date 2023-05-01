<?php

namespace Base\Twig;

use Base\Service\Localizer;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\ReferrerInterface;
use Base\Service\SettingBag;
use Base\Service\SettingBagInterface;
use Base\Traits\ProxyTrait;
use Base\Twig\Variable\BackofficeVariable;
use Base\Twig\Variable\EasyAdminVariable;
use Base\Twig\Variable\EmailVariable;
use Base\Twig\Variable\RandomVariable;
use Base\Twig\Variable\SiteVariable;
use Twig\Environment;

/**
 *
 */
class AppVariable
{
    use ProxyTrait;

    protected array $meta;

    public ReferrerInterface $referrer;

    public Environment $twig;

    public SettingBagInterface $settingBag;

    public ParameterBagInterface $parameterBag;

    public EasyAdminVariable $ea;

    public SiteVariable $site;

    public RandomVariable $random;

    public EmailVariable $email;

    public BackofficeVariable $backoffice;

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

    /**
     * @return array
     * @throws \Exception
     */
    public function getGlobals()
    {
        return array_transforms(
            fn($k, $v): ?array => 'app' != $k && str_starts_with($k, 'app') ? [str_strip($k, 'app.'), $v] : null,
            $this->twig->getGlobals()
        );
    }

    /**
     * @param string|null $key
     * @param array|null $bag
     * @return array|ParameterBagInterface|bool|float|int|string|\UnitEnum|null
     */
    public function bag(?string $key = null, ?array $bag = null)
    {
        return $key ? $this->parameterBag->get($key, $bag) ?? null : $this->parameterBag;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function settings()
    {
        return $this->settingBag->get('app.settings') ?? [];
    }

    /**
     * @return ReferrerInterface
     */
    public function referrer()
    {
        return $this->referrer;
    }

    /**
     * @return array
     */
    public function locale()
    {
        return [
            '_self' => $this->localizer->getLocale(),
            'lang' => $this->localizer->getLocaleLang(),
            'country' => $this->localizer->getLocaleCountry(),
        ];
    }
}
