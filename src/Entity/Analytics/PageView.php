<?php

namespace Base\Entity\Analytics;

use Base\Repository\Analytics\PageViewRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row per (path, HOUR, source) - an hourly rolling aggregate, not a
 * per-request event log. $views is incremented atomically (see
 * PageViewRepository, which buckets any timestamp down to the top of its
 * hour before writing) on every trackable request; time windows ("24h",
 * "7 days", "all time") are just a SUM over however many of these rows
 * fall in range, and a calendar-day figure (dailyBreakdown()) is itself a
 * SUM over that day's up-to-24 hourly rows rather than its own storage
 * grain - this is what lets "today" plot an hour-by-hour curve instead of
 * the single flat point a pure daily rollup could ever produce.
 *
 * $source splits that aggregate three ways (see Base\Service\Analytics\
 * UserAgentClassifier, which is what decides it at track() time) so a
 * dashboard can tell real visitors apart from search/monitoring crawlers
 * (SOURCE_BOT) and AI/LLM crawlers (SOURCE_AI) instead of one number that
 * silently mixes all three. Rows written before this column existed (or by
 * a raw INSERT that omits it, as the test suite's seed data does) default
 * to SOURCE_HUMAN - the historic assumption this table always made.
 */
#[ORM\Entity(repositoryClass: PageViewRepository::class)]
#[ORM\Table(name: "analytics_page_view")]
#[ORM\UniqueConstraint(name: "page_view_path_date_source", columns: ["path", "date", "source"])]
#[ORM\Index(name: "page_view_date", columns: ["date"])]
#[ORM\Index(name: "page_view_source_date", columns: ["source", "date"])]
class PageView
{
    public const SOURCE_HUMAN = "human";
    public const SOURCE_BOT = "bot";
    public const SOURCE_AI = "ai";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\Column(type: "string", length: 255)]
    protected $path;

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    #[ORM\Column(type: "datetime_immutable")]
    protected $date;

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;
        return $this;
    }

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    protected $views = 0;

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): self
    {
        $this->views = $views;
        return $this;
    }

    #[ORM\Column(type: "string", length: 16, options: ["default" => self::SOURCE_HUMAN])]
    protected $source = self::SOURCE_HUMAN;

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }
}
