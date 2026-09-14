<?php

namespace Base\Entity\Analytics;

use Base\Repository\Analytics\PageVisitRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An HOURLY PRESENCE record per page: at most one row per (hour, path,
 * subjectType, subjectId), inserted on first sight and ignored afterwards -
 * the same mechanism as Visit, with the path added.
 *
 * It exists because neither existing table can answer "unique views".
 * PageView is a raw hit counter per (path, hour, source), so reloading a page
 * ten times is ten views; Visit knows who came but not what they looked at,
 * and nothing links the two. A unique view is one subject on one page, which
 * is exactly the row this table keeps. PageView stays the non-unique count.
 *
 * Human traffic only, and only for an identified subject: a consented visitor
 * cookie or an authenticated user - see Analytics::track().
 */
#[ORM\Entity(repositoryClass: PageVisitRepository::class)]
#[ORM\Table(name: "analytics_page_visit")]
#[ORM\UniqueConstraint(name: "page_visit_subject", columns: ["date", "path", "subject_type", "subject_id"])]
#[ORM\Index(name: "page_visit_date", columns: ["date"])]
class PageVisit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
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

    #[ORM\Column(name: "subject_type", type: "string", length: 16)]
    protected $subjectType;

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function setSubjectType(string $subjectType): self
    {
        $this->subjectType = $subjectType;
        return $this;
    }

    #[ORM\Column(name: "subject_id", type: "string", length: 64)]
    protected $subjectId;

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    public function setSubjectId(string $subjectId): self
    {
        $this->subjectId = $subjectId;
        return $this;
    }
}
