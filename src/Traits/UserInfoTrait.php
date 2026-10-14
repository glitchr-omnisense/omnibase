<?php

namespace Base\Traits;

use Symfony\Component\Intl\Timezones;
use Doctrine\ORM\Mapping as ORM;

trait UserInfoTrait
{
    public const __COOKIE_IDENTIFIER__ = "USER/INFO";
    /** The time zone, where the visitor answers for it apart (the LOCATION category of the page's script, assets/styles/js/user.js). */
    public const __TIMEZONE_IDENTIFIER__ = "LOCATION/TIMEZONE";

    /**
     * What the page's script wrote of the visitor's browser: its language,
     * calendar, light or dark theme (USER/INFO), its time zone
     * (LOCATION/TIMEZONE, else USER/INFO's "timezone") and, read from that
     * zone, its country. Nothing is written here: the cookies are the
     * visitor's to accept or refuse, and the script's to write.
     *
     * @param string|null $key
     * @return array|mixed|string|null
     */
    public static function getCookie(?string $key = null)
    {
        $cookie = json_decode($_COOKIE[self::__COOKIE_IDENTIFIER__] ?? "", true);
        if (!is_array($cookie)) {
            $cookie = [];
        }

        $timezone = $_COOKIE[self::__TIMEZONE_IDENTIFIER__] ?? null;
        if (is_string($timezone) && "" !== $timezone) {
            $cookie["timezone"] = $timezone;
        }

        // The browser's zone, as its country: not every zone has one ("UTC",
        // "Etc/UTC" - what Tor Browser, Firefox resisting fingerprinting and
        // many containers report), and Timezones::getCountryCode() throws for
        // those: an exception on every page for these visitors.
        $timezone = $cookie["timezone"] ?? null;
        if (is_string($timezone) && Timezones::exists($timezone)) {
            try {
                $cookie["country"] = Timezones::getCountryCode($timezone);
            } catch (\Throwable) {
                // No country for this zone: what the cookie says of it, if anything.
            }
        }

        if (empty($key)) {
            return $cookie;
        }

        return $cookie[$key] ?? null;
    }

    /**
     * @param string $key
     * @param $value
     * @param int $lifetime
     * @return void
     */
    public static function setCookie(string $key, $value, int $lifetime = 0)
    {
        $cookie = json_decode($_COOKIE[self::__COOKIE_IDENTIFIER__] ?? "", true) ?? [];
        $cookie = array_merge($cookie, [$key => $value]);

        setcookie(self::__COOKIE_IDENTIFIER__, json_encode($cookie), $lifetime > 0 ? time() + $lifetime : 0, "/", parse_url2(get_url())["domain"] ?? "");
    }

    public static function getAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    public static function getIp(): ?string
    {
        $keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) {
                return $_SERVER[$k];
            }
        }
        return null;
    }

    #[ORM\Column(type:"string", length:255, nullable:true)]
    protected $timezone;
    public function getCountryCode(): ?string
    {
        try {
            $countryCode = Timezones::getCountryCode($this->getTimezone());
        } catch (\Exception $e) {
            $countryCode = null;
        }

        return $countryCode;
    }

    public function getTimezone(): string
    {
        return $this->timezone ?? "UTC";
    }

    public function setTimezone(?string $timezone = null): self
    {
        if (empty($timezone)) {
            $timezone = $this->timezone ?? null;
        }
        
        $this->timezone = $timezone ?? static::getCookie("timezone") ?? null;
        if (!in_array($this->timezone, timezone_identifiers_list())) {
            $this->timezone = "UTC";
        }

        return $this;
    }
}
