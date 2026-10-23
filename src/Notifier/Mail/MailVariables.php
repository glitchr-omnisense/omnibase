<?php

namespace Base\Notifier\Mail;

use Base\Service\Launcher;
use Base\Service\SettingBagInterface;

/**
 * What omnibase's e-mail templates read of `app` (the site's email.html.twig:
 * app.site.domain, app.site.logo, app.email.title, app.email.age...), for a
 * host whose Twig has no omnibase AppVariable (omnibridge/laravel): the
 * same settings read the same way as SiteVariable and EmailVariable read
 * them under Symfony - the site's logo, the e-mails' title, slogan and logo
 * (each the site's when the e-mails have none of their own), the contact
 * address, the years since the launch (Launcher::since()), the request's
 * domain. EmailComposer gives it to the template as `app`, where the host
 * has no such global.
 */
final class MailVariables
{
    public readonly object $site;
    public readonly object $email;

    /**
     * @param \Closure(): ?string $domain   the request's domain (AdvancedRouter::getDomain()), null outside a request
     * @param \Closure(): ?string $homepage the site's index address (EmailVariable::homepage())
     */
    public function __construct(SettingBagInterface $settings, \Closure $domain, ?\Closure $homepage = null)
    {
        $setting = static fn (string $path, ?string $else = null): mixed => $settings->getScalar($path) ?? (null !== $else ? $settings->getScalar($else) : null);
        $this->site = new class($setting, $domain) {
            public function __construct(private readonly \Closure $setting, private readonly \Closure $domain)
            {
            }

            public function domain(): ?string
            {
                return ($this->domain)();
            }

            public function logo(): mixed
            {
                return ($this->setting)('base.settings.logo');
            }
        };
        $this->email = new class($setting, $homepage) {
            public function __construct(private readonly \Closure $setting, private readonly ?\Closure $homepage)
            {
            }

            public function homepage(): ?string
            {
                return null !== $this->homepage ? ($this->homepage)() : null;
            }

            public function title(): mixed
            {
                return ($this->setting)('base.settings.title.email', 'base.settings.title');
            }

            public function slogan(): mixed
            {
                return ($this->setting)('base.settings.slogan.email', 'base.settings.slogan');
            }

            public function logo(): mixed
            {
                return ($this->setting)('base.settings.logo.email', 'base.settings.logo');
            }

            public function address(): mixed
            {
                return ($this->setting)('base.settings.mail.contact');
            }

            public function age(): string
            {
                return Launcher::yearsSince(Launcher::date(($this->setting)('base.settings.launchdate')));
            }
        };
    }
}
