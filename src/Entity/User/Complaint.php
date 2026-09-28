<?php

namespace Base\Entity\User;

use Base\Entity\User;
use Base\Repository\User\ComplaintRepository;
use Base\Service\Model\IconizeInterface;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user's complaint about another one (or about something), for the staff to answer.
 *
 * Where it was made is the app's business: `source` names it (a game world, a forum…) and `location` where in
 * it (a room, a topic), and `context` carries whatever makes it answerable there - for a game world, what was
 * said in the room before it and every act of the staff that touched the people in it. The two users are kept
 * by name as well as by account: an account may go, the complaint stays readable. `targetStatus` is the rank the
 * one complained about held when it was made.
 *
 * Who may read one is Base\Security\Voter\ComplaintVoter's call (COMPLAINT_READ / COMPLAINT_HANDLE on the
 * complaint): the staff whose roles outrank the one it is about - a complaint about a moderator goes to the ranks
 * above them, never to the moderator - and never the one it is about.
 *
 * Settled, it may point at the Sanction it led to.
 */
#[ORM\Entity(repositoryClass: ComplaintRepository::class)]
#[ORM\Table(name: "userComplaint")]
#[ORM\Index(name: "idx_complaint_status", columns: ["status", "createdAt"])]
class Complaint implements IconizeInterface
{
    public const OPEN = "open";
    public const HANDLED = "handled";
    public const DISMISSED = "dismissed";
    public const STATUSES = [self::OPEN => "À traiter", self::HANDLED => "Traitée", self::DISMISSED => "Classée sans suite"];

    public function __construct(?User $complainant = null, ?string $text = null, ?string $source = null)
    {
        $this->createdAt = new DateTime("now");
        $this->complainant = $complainant;
        $this->complainantName = $complainant ? (string) $complainant : "";
        $this->text = (string) $text;
        $this->source = $source;
    }

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-hand"];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    public function getId(): ?int { return $this->id; }

    /** Mutable: base-bundle's |datetime filter takes a \DateTime only. */
    #[ORM\Column(type: "datetime")]
    protected DateTime $createdAt;

    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function setCreatedAt(DateTime $at): self { $this->createdAt = $at; return $this; }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    protected ?User $complainant = null;

    public function getComplainant(): ?User { return $this->complainant; }
    public function setComplainant(?User $user): self { $this->complainant = $user; return $this; }

    #[ORM\Column(type: "string", length: 64)]
    protected string $complainantName = "";

    public function getComplainantName(): string { return $this->complainantName; }
    public function setComplainantName(string $name): self { $this->complainantName = mb_substr($name, 0, 64); return $this; }

    /** Who it is about, when it is about somebody with an account. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    protected ?User $target = null;

    public function getTarget(): ?User { return $this->target; }
    public function setTarget(?User $user): self { $this->target = $user; return $this; }

    #[ORM\Column(type: "string", length: 64, nullable: true)]
    protected ?string $targetName = null;

    public function getTargetName(): ?string { return $this->targetName; }
    public function setTargetName(?string $name): self { $this->targetName = null === $name ? null : mb_substr($name, 0, 64); return $this; }

    #[ORM\Column(type: "string", length: 32, nullable: true)]
    protected ?string $targetStatus = null;

    public function getTargetStatus(): ?string { return $this->targetStatus; }
    public function setTargetStatus(?string $status): self { $this->targetStatus = null === $status ? null : mb_substr($status, 0, 32); return $this; }

    /** Where it was made: "world" (a game), "forum"… */
    #[ORM\Column(type: "string", length: 32, nullable: true)]
    protected ?string $source = null;

    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $source): self { $this->source = null === $source ? null : mb_substr($source, 0, 32); return $this; }

    /** Where in it: a room's id, a topic's… and its name as shown. */
    #[ORM\Column(type: "string", length: 64, nullable: true)]
    protected ?string $location = null;

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = null === $location ? null : mb_substr($location, 0, 64); return $this; }

    #[ORM\Column(type: "string", length: 128, nullable: true)]
    protected ?string $locationName = null;

    public function getLocationName(): ?string { return $this->locationName; }
    public function setLocationName(?string $name): self { $this->locationName = null === $name ? null : mb_substr($name, 0, 128); return $this; }

    #[ORM\Column(type: "text")]
    protected string $text = "";

    public function getText(): string { return $this->text; }
    public function setText(string $text): self { $this->text = $text; return $this; }

    /** Whatever the source hands over to make it answerable. */
    #[ORM\Column(type: "json")]
    protected array $context = [];

    public function getContext(): array { return $this->context; }
    public function setContext(array $context): self { $this->context = $context; return $this; }

    #[ORM\Column(type: "string", length: 16, options: ["default" => self::OPEN])]
    protected string $status = self::OPEN;

    public function getStatus(): string { return $this->status; }
    public function getStatusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function isOpen(): bool { return self::OPEN === $this->status; }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    protected ?User $handledBy = null;

    public function getHandledBy(): ?User { return $this->handledBy; }

    #[ORM\Column(type: "datetime", nullable: true)]
    protected ?DateTime $handledAt = null;

    public function getHandledAt(): ?DateTime { return $this->handledAt; }

    /** What the staff answered. */
    #[ORM\Column(type: "text", nullable: true)]
    protected ?string $answer = null;

    public function getAnswer(): ?string { return $this->answer; }

    /** The sanction it led to, if any. */
    #[ORM\ManyToOne(targetEntity: Sanction::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    protected ?Sanction $sanction = null;

    public function getSanction(): ?Sanction { return $this->sanction; }
    public function setSanction(?Sanction $sanction): self { $this->sanction = $sanction; return $this; }

    /** Handled or dismissed (or opened again), by whom, with what answer. */
    public function settle(string $status, ?User $by, ?string $answer = null): self
    {
        if (!isset(self::STATUSES[$status])) {
            throw new \InvalidArgumentException("Statut inconnu : {$status}");
        }
        $this->status = $status;
        $this->answer = null !== $answer && "" !== trim($answer) ? trim($answer) : null;
        $this->handledBy = self::OPEN === $status ? null : $by;
        $this->handledAt = self::OPEN === $status ? null : new DateTime("now");

        return $this;
    }
}
