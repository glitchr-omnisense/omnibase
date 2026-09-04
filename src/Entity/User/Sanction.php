<?php

namespace Base\Entity\User;

use Base\Entity\User;
use Base\Repository\User\SanctionRepository;
use Base\Service\Model\IconizeInterface;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * One penalty, given to one user, once.
 *
 * The counterpart to Penalty: that one is the catalogue an administrator
 * curates, this one is a moderator picking an entry from it and applying it.
 * Everything that varies per case lives here - who received it, who issued
 * it, why, and until when - while what the penalty IS and what it costs stays
 * in the catalogue entry, so an administrator can restate the cost of "spam"
 * in one place.
 *
 * This exists as an entity rather than the join table it replaces
 * (user_penalties) because a join row has room for nothing but the two ids.
 * With one, every application of the same penalty would have shared a single
 * expiry, and there would have been nowhere to record the moderator or the
 * reason - the two things anybody asks for first when a sanction is disputed.
 *
 * Expiry is stored, not derived. Penalty::expiresFrom() decides it when the
 * sanction is created, and it is then frozen: shortening "spam" in the
 * catalogue must not silently pardon everyone already serving one, and
 * lengthening it must not silently extend them either.
 */
#[ORM\Entity(repositoryClass: SanctionRepository::class)]
#[ORM\Table(name: "userSanction")]
#[ORM\Index(name: "idx_sanction_user_expiry", columns: ["user_id", "expiresAt"])]
class Sanction implements IconizeInterface
{
    public function __construct(?User $user = null, ?Penalty $penalty = null, ?User $moderator = null, ?string $reason = null)
    {
        $this->createdAt = new DateTime("now");
        $this->user = $user;
        $this->penalty = $penalty;
        $this->moderator = $moderator;
        $this->reason = $reason;

        // Frozen at issue time from the catalogue's duration - see the class
        // comment on why this is not recomputed on read.
        $this->expiresAt = $penalty?->expiresFrom($this->createdAt);
    }

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-gavel"];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "sanctions")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    protected $user;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Penalty::class, inversedBy: "sanctions")]
    #[ORM\JoinColumn(nullable: false)]
    protected $penalty;

    public function getPenalty(): ?Penalty
    {
        return $this->penalty;
    }

    public function setPenalty(?Penalty $penalty): self
    {
        $this->penalty = $penalty;

        return $this;
    }

    /**
     * Who issued it. Nullable because the site itself can sanction somebody
     * automatically, and pretending a human did would be worse than a blank.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    protected $moderator;

    public function getModerator(): ?User
    {
        return $this->moderator;
    }

    public function setModerator(?User $moderator): self
    {
        $this->moderator = $moderator;

        return $this;
    }

    #[ORM\Column(type: "text", nullable: true)]
    protected $reason;

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    #[ORM\Column(type: "datetime")]
    protected $createdAt;

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /** Null means it stands until a moderator lifts it. */
    #[ORM\Column(type: "datetime", nullable: true)]
    protected $expiresAt;

    public function getExpiresAt(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * Lifting a sanction expires it rather than deleting it, so the history
     * of what somebody was sanctioned for survives being forgiven for it.
     */
    #[ORM\Column(type: "datetime", nullable: true)]
    protected $liftedAt;

    public function getLiftedAt(): ?DateTimeInterface
    {
        return $this->liftedAt;
    }

    public function lift(?DateTimeInterface $at = null): self
    {
        $this->liftedAt = $at ? DateTime::createFromInterface($at) : new DateTime("now");

        return $this;
    }

    public function isActive(?DateTimeInterface $at = null): bool
    {
        $at = $at ?? new DateTime("now");

        if ($this->liftedAt !== null && $this->liftedAt <= $at) {
            return false;
        }

        return $this->expiresAt === null || $this->expiresAt > $at;
    }

    /** What this contributes to a score right now - nothing once it has lapsed. */
    public function getWeight(?DateTimeInterface $at = null): int
    {
        return $this->isActive($at) ? ($this->penalty?->getWeight() ?? 0) : 0;
    }
}
