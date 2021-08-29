<?php

namespace Base\Entity\User;

use App\Entity\User;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\User\TokenRepository;
use Base\Service\BaseService;
use Base\Twig\BaseTwigExtension;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Hashids\Hashids;

/**
 * @ORM\Entity(repositoryClass=TokenRepository::class)
 */
class Token
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $value;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="tokens")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $expireAt;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isRevoked;

    public function __construct(string $name, $dT = null)
    {
        $this->name = $name;
        $this->isRevoked = false;
        $this->expireAt = null;

        $this->hashIds = new Hashids();
        $this->generate($dT);
    }

    public function __sleep()
    {
        $this->translator = null;

        return array_keys(get_object_vars($this));
    }
    
    public function __toString()
    {
        return    $this->name .
            " " . $this->get() .
            " " . $this->getCreatedAt()->getTimestamp() .
            " " . $this->getLifetime() .
            " " . $this->isRevoked();
    }

    public function encode()
    {
        $hex = bin2hex($this->__toString());
        return $this->hashIds->encodeHex($hex);
    }

    public function decode(string $hash)
    {
        $hex = $this->hashIds->decodeHex($hash);
        $str = hex2bin($hex);

        list($name, $value, $timestamp, $dT, $isRevoked) = explode(" ", $str);
        $this->name = $name;
        $this->value = $value;

        // Creation time
        $createdAt = new \DateTime();
        $createdAt->setTimestamp($timestamp);
        $this->setCreatedAt($createdAt);

        // Expiry date calculation
        if($dT < 0) $dT = null;
        if($dT) {
            $expiry = clone $createdAt;
            $expiry->modify(is_numeric($dT) ? "+ ".floor($dT)." seconds" : $dT);
            $this->setExpiry($expiry);
        }

        $this->isRevoked = $isRevoked;

        return $this;
    }

    public function get(): ?string
    {
        return $this->value;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function generate($dT = null): self
    {
        // Creation date
        $now = new \DateTime("now");
        $this->setCreatedAt($now);

        // Expiry date calculation
        if($dT) {
            $expiry = clone $now;
            $expiry->modify(is_numeric($dT) ? "+ ".floor($dT)." seconds" : $dT);
            $this->setExpiry($expiry);
        }

        // Generate token value
        $this->value = rtrim(str_replace(["+","/"], ["",""], base64_encode(random_bytes(16))), '=');

        return $this;
    }

    public function revoke($isRevoked = true): self { return $this->setIsRevoked($isRevoked); }
    public function isRevoked(): bool { return $this->isRevoked; }
    public function setIsRevoked($isRevoked = true): self
    {
        $this->isRevoked = $isRevoked;
        return $this;
    }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self
    {
        $this->user = $user;
        if($this->user)
		$this->user->addToken($this);

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpireAt(): ?\DateTimeInterface { return $this->expireAt; }
    public function setExpireAt(\DateTimeInterface $expireAt): self
    {
        if (!$this->getCreatedAt())
            $this->setCreatedAt(new \DateTime("now"));

        if ($expireAt < $this->getCreatedAt())
            $expireAt = $this->createdAt;

        $this->expireAt = $expireAt;
        return $this;
    }

    public function getExpiry(): ?\DateTimeInterface { return $this->getExpireAt(); }
    public function setExpiry(\DateTimeInterface $expireAt): self { return $this->setExpireAt($expireAt); }
    public function hasExpiry():bool { return $this->getExpireAt() == $this->getCreatedAt(); }
    public function isValid():bool { return !$this->isHit() && !$this->isRevoked(); }
    public function isHit():bool { return ($this->getExpiry() == null ? false : new \DateTime("now") >= $this->getExpiry()); }
    public function getElapsedTime():int { return time() - $this->createdAt->getTimestamp(); }
    public function getLifetime():int { return ($this->expireAt == null ? -1 : $this->expireAt->getTimestamp() - $this->createdAt->getTimestamp()); }
    public function getRemainingTime():int { return $this->expireAt->getTimestamp() - time(); }
    public function getRemainingTimeStr(): string { return BaseService::getTwigExtension()->time($this->getRemainingTime()); }
}