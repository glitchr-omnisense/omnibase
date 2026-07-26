<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Repository\User\PasskeyRepository;
use Base\Database\Attribute\Timestamp;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\CredentialRecord;

#[ORM\Entity(repositoryClass: PasskeyRepository::class)]
class Passkey extends CredentialRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "passkeys")]
    #[ORM\JoinColumn(nullable: false)]
    protected $user;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        if ($this->user && $this->user != $user) {
            $this->user->removePasskey($this);
        }

        $user?->addPasskey($this);

        $this->user = $user;
        return $this;
    }

    /**
     * User-assigned name shown in Settings so multiple passkeys can be told apart
     * (e.g. "MacBook Touch ID", "YubiKey").
     */
    #[ORM\Column(type: "string", length: 100, nullable: true)]
    protected $label;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    #[ORM\Column(type: "datetime")]
    #[Timestamp(on: "create")]
    protected $createdAt;

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    #[ORM\Column(type: "datetime", nullable: true)]
    protected $lastUsedAt;

    public function getLastUsedAt(): ?DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    public function touch(): self
    {
        $this->lastUsedAt = new \DateTime();
        return $this;
    }

    /**
     * The library hands saveCredentialRecord() a plain CredentialRecord (built internally
     * from the attestation response), never an instance of this class - copy its fields
     * across into a real, persistable Passkey tied to the registering user.
     */
    public static function createFromCredentialRecord(CredentialRecord $record, User $user, ?string $label = null): self
    {
        $passkey = new self(
            $record->publicKeyCredentialId,
            $record->type,
            $record->transports,
            $record->attestationType,
            $record->trustPath,
            $record->aaguid,
            $record->credentialPublicKey,
            $record->userHandle,
            $record->counter,
            $record->otherUI,
            $record->backupEligible,
            $record->backupStatus,
            $record->uvInitialized,
        );

        $passkey->setUser($user);
        $passkey->setLabel($label);

        return $passkey;
    }
}
