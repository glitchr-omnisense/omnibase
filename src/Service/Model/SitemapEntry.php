<?php

namespace Base\Service\Model;

class SitemapEntry
{
    protected string $loc;
    public function getLoc(): ?string
    {
        return $this->loc;
    }

    protected string $locale;
    public function getLocale(): ?string
    {
        return $this->locale;
    }
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    protected float $priority;
    public function getPriority(): ?float
    {
        return $this->priority;
    }
    public function setPriority(float $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    protected string $lastMod;
    public function getLastMod(): ?string
    {
        return $this->lastMod;
    }
    public function setLastMod(string $lastMod): self
    {
        $this->lastMod = $lastMod;
        return $this;
    }

    protected string $changeFreq;
    public function getChangeFreq(): ?string
    {
        return $this->changeFreq;
    }
    public function setChangeFreq(string $changeFreq): self
    {
        $this->changeFreq = $changeFreq;
        return $this;
    }

    protected array $alternates = [];
    public function hasAlternate(SitemapEntry $entry)
    {
        return in_array($entry, $this->alternates);
    }
    public function getAlternates(): array
    {
        return $this->alternates;
    }
    public function addAlternate(SitemapEntry $entry): self
    {
        if (in_array($entry, $this->alternates)) {
            return $this;
        }

        $this->alternates[] = $entry;
        return $this;
    }

    public function removeAlternate(SitemapEntry $entry): self
    {
        if (($index = array_search($entry, $this->alternates)) === false) {
            return $this;
        }

        unset($this->alternates[$index]);
        return $this;
    }

    public function __construct($loc)
    {
        $this->loc = $loc;
    }

    public function toArray(): array
    {
        $entry = [
            "loc" => $this->loc,
            "priority" => $this->priority,
            "lastmod" => $this->lastMod,
            "changefreq" => $this->changeFreq,
            "alternates" => []
        ];

        foreach ($this->alternates as $alternate) {
            if (in_array($alternate->getLocale(), array_column($entry["alternates"], "locale"))) {
                continue;
            }

            $entry["alternates"][] = [
                "href" => $alternate->getLoc(),
                "locale" => $alternate->getLocale()
            ];
        }

        return $entry;
    }
}
