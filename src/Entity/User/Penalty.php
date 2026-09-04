<?php

namespace Base\Entity\User;

use Base\Entity\User\Group;
use Base\Service\Model\IconizeInterface;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\PenaltyRepository;

/**
 * A penalty an administrator has defined, NOT one somebody has received.
 *
 * This is a catalogue: an administrator writes the set of penalties the site
 * recognises once - what each is called, what it costs, how long it lasts -
 * and a moderator then applies one from that list rather than inventing a
 * sanction and a number on the spot. Applying one creates a Sanction, which
 * is where the per-case facts live (who, by whom, why, until when).
 *
 * That split is why the user side is a Sanction and not the plain
 * many-to-many it used to be: a join table has nowhere to record when a
 * penalty expires or who issued it, so every application of the same penalty
 * would have had to share one expiry.
 *
 * Groups keep a direct many-to-many, because a penalty carried by a group is
 * a property of the group itself rather than an incident with a date.
 */
#[ORM\Entity(repositoryClass: PenaltyRepository::class)]
class Penalty implements IconizeInterface
{
    public function __construct(?string $type = null, int $weight = 0)
    {
        $this->gid = new ArrayCollection();
        $this->sanctions = new ArrayCollection();

        $this->type = $type;
        $this->weight = $weight;
        $this->createdAt = new DateTime("now");
    }

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-exclamation-triangle"];
    }

    public function __toString(): string
    {
        return $this->type ?? "penalty#" . $this->id;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\ManyToMany(targetEntity:Group::class, mappedBy:"penalties")]
    protected $gid;

    #[ORM\OneToMany(targetEntity:Sanction::class, mappedBy:"penalty")]
    protected $sanctions;

    #[ORM\Column(type:"string", length:255)]
    protected $type;

    /**
     * What receiving this costs, in points.
     *
     * Signed on purpose. The scoring side sums whatever it finds without
     * caring about the sign, so the same catalogue can hold a "spam" penalty
     * worth -50 and a "verified contributor" credit worth +20 - which is what
     * lets one score answer both "is this account in trouble" and "has this
     * account earned its way into that group".
     */
    #[ORM\Column(type:"integer", options:["default" => 0])]
    protected $weight = 0;

    /**
     * How long a Sanction created from this lasts, in days. Null means it
     * does not expire on its own and a moderator has to lift it.
     *
     * Days rather than a datetime: this is the catalogue entry, so it has to
     * say "two weeks", not "until the 4th of September". The instant is
     * computed per application, in expiresFrom() below.
     */
    #[ORM\Column(type:"integer", nullable:true)]
    protected $durationDays;

    #[ORM\Column(type:"text")]
    protected $extra;

    #[ORM\Column(type: "datetime")]
    protected $createdAt;

    public function getFormType(): ?string
    {
        return $this->type;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight ?? 0;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(?int $durationDays): self
    {
        $this->durationDays = $durationDays;

        return $this;
    }

    /**
     * When a sanction issued at $from would lapse, or null for one that never
     * does. Kept here rather than in the moderator's hands so that changing a
     * penalty's length in the catalogue changes it everywhere it is applied
     * next, which is the point of having a catalogue.
     */
    public function expiresFrom(?DateTimeInterface $from = null): ?DateTime
    {
        if ($this->durationDays === null) {
            return null;
        }

        $from = $from ? DateTime::createFromInterface($from) : new DateTime("now");

        return $from->add(new DateInterval("P" . max(0, $this->durationDays) . "D"));
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function setExtra(string $extra): self
    {
        $this->extra = $extra;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getSanctions(): Collection
    {
        return $this->sanctions;
    }

    /**
     * @return Collection
     */
    public function getGid(): Collection
    {
        return $this->gid;
    }

    public function addGid(Group $gid): self
    {
        if (!$this->gid->contains($gid)) {
            $this->gid[] = $gid;
            $gid->addPenalty($this);
        }

        return $this;
    }

    public function removeGid(Group $gid): self
    {
        if ($this->gid->removeElement($gid)) {
            $gid->removePenalty($this);
        }

        return $this;
    }
}
