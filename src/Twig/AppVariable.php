<?php

namespace Base\Twig;

use Base\Component\HttpFoundation\Referrer;
use Base\Service\SettingBag;
use Base\Service\ParameterBagInterface;
use Base\Traits\ProxyTrait;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
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

    public function __construct(\Symfony\Bridge\Twig\AppVariable $appVariable, SettingBag $settingBag, ParameterBagInterface $parameterBag, Referrer $referrer, Environment $twig, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->settingBag  = $settingBag;
        $this->referrer  = $referrer;
        $this->twig      = $twig;
        $this->bag       = $parameterBag;

        $this->meta      = [];
        $this->random    = new RandomVariable();
        $this->easyadmin = new EasyAdminVariable($adminUrlGenerator);
        $this->setProxy($appVariable);
    }

    public function bag(?string $key = null, ?array $bag = null) { return $key ? $this->bag->get($key, $bag) ?? null : $this->bag; }
    public function settings() { return $this->settingBag->get("app.settings") ?? []; }
    public function referrer() { return $this->referrer; }

    public function meta(array $meta = []) { return $this->meta = array_merge($this->meta, $meta); }

    public function getGlobals() {

        return array_transforms(
            fn($k,$v):?array => $k != "app" && str_starts_with($k, "app") ? [str_strip($k, "app."), $v] : null,
            $this->twig->getGlobals());
    }
}
