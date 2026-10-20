<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Database\Attribute\Timestamp;
use Base\Database\Type\Passkey\Aaguid;
use Base\Database\Type\Passkey\Base64;
use Base\Database\Type\Passkey\TrustPath;
use Base\Repository\User\PasskeyRepository;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * A passkey of an account (WebAuthn's credential record), described by the
 * core (plan "omniauth", AU1; decision O6): its 13 fields are its own
 * attributes, no longer inherited from web-auth's Webauthn\CredentialRecord
 * and mapped by WebauthnBundle's XML, so the table is described once, read the
 * same by any host (the Laravel twin maps it as Symfony does) and the entity
 * loads nothing of webauthn-lib. The columns are those the bundle made
 * (userPasskey, Version20260726133116 on lesringostars): the same names, and
 * the core's types Base\Database\Type\Passkey\{Base64, Aaguid, TrustPath}
 * declare the same SQL and write the same text as the bundle's `base64`,
 * `aaguid` and `trust_path` - no migration, a row written by either read by
 * the other.
 *
 * The fields keep CredentialRecord's public names; their values are plain:
 * the credential id and the public key as bytes, the AAGUID as its text, the
 * trust path as the array webauthn-lib normalizes it to. The objects of the
 * library are made from it where the library is used:
 * Base\Repository\User\PasskeyRepository gives WebauthnBundle the
 * CredentialRecord it asks for and saves what it hands back.
 */
#[ORM\Entity(repositoryClass: PasskeyRepository::class)]
class Passkey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    /** The credential's id: bytes, unique to the authenticator. */
    #[ORM\Column(type: Base64::NAME)]
    public string $publicKeyCredentialId;

    #[ORM\Column(type: "string")]
    public string $type = 'public-key';

    /** @var list<string> how the browser reaches the authenticator ("internal", "usb", "hybrid"...) */
    #[ORM\Column(type: "json")]
    public array $transports = [];

    #[ORM\Column(type: "string")]
    public string $attestationType = 'none';

    /** @var array<string, mixed> [] for none, ["x5c" => [...]] for certificates (webauthn-lib's normal form) */
    #[ORM\Column(type: TrustPath::NAME)]
    public array $trustPath = [];

    /** The authenticator's model, a UUID's text (all zeros: none said). */
    #[ORM\Column(type: Aaguid::NAME, length: 36)]
    public string $aaguid = '00000000-0000-0000-0000-000000000000';

    /** The public key, COSE-encoded bytes. */
    #[ORM\Column(type: Base64::NAME)]
    public string $credentialPublicKey;

    /** The account's handle (its id, as text). */
    #[ORM\Column(type: "string")]
    public string $userHandle;

    /** The signature counter the authenticator last gave. */
    #[ORM\Column(type: "integer")]
    public int $counter = 0;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: "json", nullable: true)]
    public ?array $otherUI = null;

    #[ORM\Column(type: "boolean", nullable: true)]
    public ?bool $backupEligible = null;

    #[ORM\Column(type: "boolean", nullable: true)]
    public ?bool $backupStatus = null;

    #[ORM\Column(type: "boolean", nullable: true)]
    public ?bool $uvInitialized = null;

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
     * A new passkey of $user, from the credential record webauthn-lib made at its registration (the
     * bundle hands saveCredentialRecord() a plain Webauthn\CredentialRecord, never an instance of
     * this class). A type in a signature loads nothing: the copy is PasskeyRepository's, the one
     * place that speaks webauthn-lib.
     */
    public static function createFromCredentialRecord(\Webauthn\CredentialRecord $record, User $user, ?string $label = null): self
    {
        $passkey = PasskeyRepository::fill(new self(), $record);
        $passkey->setUser($user);
        $passkey->setLabel($label);

        return $passkey;
    }
}
