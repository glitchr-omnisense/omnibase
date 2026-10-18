<?php

namespace Base\Twig\Variable;

use Base\Routing\AdvancedRouterInterface;
use Base\Service\LauncherInterface;
use Base\Service\SettingBagInterface;

/**
 * The site as an e-mail names it: the e-mail's own title, slogan and logo set in
 * the back office, else the site's. Read from the settings and the router
 * themselves, where it asked BaseService (plan "omnibase agnostique", C9).
 */
class EmailVariable
{
    public function __construct(
        protected AdvancedRouterInterface $router,
        protected SettingBagInterface $settingBag,
        protected LauncherInterface $launcher,
    ) {
    }

    /**
     * @return string
     */
    public function homepage()
    {
        return $this->router->getRouteIndex();
    }

    public function title(): ?string
    {
        return $this->setting("base.settings.title.email", "base.settings.title");
    }

    public function slogan(): ?string
    {
        return $this->setting("base.settings.slogan.email", "base.settings.slogan");
    }

    public function logo(): ?string
    {
        return $this->setting("base.settings.logo.email", "base.settings.logo");
    }

    public function address(?string $locale = null): ?string
    {
        return $this->setting("base.settings.mail.contact");
    }

    public function age(?string $locale = null): string
    {
        return $this->launcher->since($locale);
    }

    private function setting(string $path, ?string $else = null): mixed
    {
        return $this->settingBag->getScalar($path) ?? ($else ? $this->settingBag->getScalar($else) : null);
    }
}
