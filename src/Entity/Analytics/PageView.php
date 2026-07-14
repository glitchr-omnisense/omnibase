<?php

namespace Base\Entity\Analytics;

use Base\Repository\Analytics\PageViewRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row per (path, day) - a daily rolling aggregate, not a per-request
 * event log. $views is incremented atomically (see PageViewRepository) on
 * every trackable request; time windows ("24h", "7 days", "all time") are
 * just a SUM over however many of these rows fall in range.
 */
#[ORM\Entity(repositoryClass: PageViewRepository::class)]
#[ORM\Table(name: "analytics_page_view")]
#[ORM\UniqueConstraint(name: "page_view_path_date", columns: ["path", "date"])]
#[ORM\Index(name: "page_view_date", columns: ["date"])]
class PageView
{
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

    #[ORM\Column(type: "date_immutable")]
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
}
