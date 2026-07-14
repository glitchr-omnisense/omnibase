<?php

namespace Base\Entity\Analytics;

use Base\Repository\Analytics\VisitRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A daily PRESENCE record, not a per-request event log: at most one row per
 * (day, subjectType, subjectId), inserted on first sight that day and
 * ignored on every later request the same day. This is what makes
 * COUNT(DISTINCT subjectId) over any date range an exact unique-visitor/
 * unique-user count instead of an approximation - unlike PageView (a raw
 * hit counter), a returning subject never inflates this table twice in one
 * day, and the table stays bounded by (subjects x days), not raw traffic.
 *
 * subjectType is "visitor" (an anonymous, cookie-identified browser - see
 * Base\Service\Analytics for the consent-gating this depends on) or "user"
 * (an authenticated Base\Entity\User's id) - two independent counters
 * sharing one mechanism rather than two parallel tables.
 */
#[ORM\Entity(repositoryClass: VisitRepository::class)]
#[ORM\Table(name: "analytics_visit")]
#[ORM\UniqueConstraint(name: "visit_subject_date", columns: ["date", "subject_type", "subject_id"])]
#[ORM\Index(name: "visit_type_date", columns: ["subject_type", "date"])]
class Visit
{
    public const TYPE_VISITOR = "visitor";
    public const TYPE_USER = "user";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
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
