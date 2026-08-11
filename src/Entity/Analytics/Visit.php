<?php

namespace Base\Entity\Analytics;

use Base\Repository\Analytics\VisitRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An HOURLY PRESENCE record, not a per-request event log: at most one row
 * per (hour, subjectType, subjectId) - see VisitRepository, which buckets
 * any timestamp down to the top of its hour before writing - inserted on
 * first sight that hour and ignored on every later request the same hour.
 * COUNT(DISTINCT subjectId) still gives an exact unique count over any
 * window regardless of grain (a subject seen in two different hours of the
 * same day contributes two rows but ONE distinct id, so a day/week/all-time
 * total is never inflated); the finer grain only changes what an hour-by-
 * hour BREAKDOWN can show (a subject active at 9am and 2pm now shows up in
 * both hourly buckets, same as it already showed up on multiple separate
 * DAYS in a multi-day breakdown before this). Unlike PageView (a raw hit
 * counter), a returning subject never inflates this table twice in the
 * same hour, and the table stays bounded by (subjects x hours), not raw
 * traffic.
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
