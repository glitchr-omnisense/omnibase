<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Annotations\Annotation\Slugify;
use Base\Model\IconizeInterface;
use Base\Service\BaseService;

use Hashids\Hashids;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\TokenRepository;
use Base\Traits\BaseTrait;

/**
 * @ORM\Entity(repositoryClass=TokenRepository::class)
 */
class Token implements IconizeInterface
{
    use BaseTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-drumstick-bite"]; } 

    public const SEPARATOR = ";";
    public const DEADTIME = 3*60; /* time before next request */
    public function __construct(string $name, ?int $expiry = null, ?int $deadtime = self::DEADTIME)
    {
        $this->name = $name;
        $this->isRevoked = false;
        $this->expireAt = null;
        $this->allowAt = null;

        $this->hashIds = new Hashids($this->getService()->getSalt());
        $this->generate($expiry, $deadtime);
    }

    public function __sleep() { return array_keys(get_object_vars($this)); }
    public function __toString()
    {
        return $this->getName() .
                self::SEPARATOR . $this->get() .
                self::SEPARATOR . $this->getCreatedAt()->getTimestamp() .
                self::SEPARATOR . $this->getLifetime() .
                self::SEPARATOR . $this->getDeadtime() .
                self::SEPARATOR . $this->isRevoked();
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

        list($name, $value, $timestamp, $expiry, $deadtime, $isRevoked) = explode(self::SEPARATOR, $str);
        $this->name = $name;
        $this->value = $value;

        // Creation time
        $createdAt = new \DateTime();
        $createdAt->setTimestamp($timestamp);
        $this->setCreatedAt($createdAt);

        // Expiry date calculation
        if($expiry < 0) $expiry = null;
        if($expiry) {
            $expireAt = clone $createdAt;
            $expireAt->modify(is_numeric($expiry) ? "+ ".floor($expiry)." seconds" : $expiry);
            $this->setExpiry($expireAt);
        }

        // Deadtime date calculation (before next request)
        if($deadtime < 0) $deadtime = null;
        if($deadtime) {
            $allowAt = clone $createdAt;
            $allowAt->modify(is_numeric($deadtime) ? "+ ".floor($deadtime)." seconds" : $deadtime);
            $this->setExpiry($allowAt);
        }

        $this->isRevoked = $isRevoked;

        return $this;
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(type="string", length=255)
     * @Slugify(separator="-", unique=false)
     */
    protected $name;

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $value;

    public function get(): ?string { return $this->getValue(); }
    public function getValue(): ?string { return $this->value; }
    public function generate(?int $expiry = null, ?int $deadtime = null): self
    {
        // Creation date
        $now = new \DateTime("now");
        $this->setCreatedAt($now);

        // Expiry date calculation
        if($expiry) {

            $expireAt = clone $now;
            $expireAt->modify(is_numeric($expiry) ? "+ ".floor($expiry)." seconds" : $expiry);
            $this->setExpiry($expireAt);
        }

        // Rate date calculation
        if($deadtime) {

            $allowAt = clone $now;
            $allowAt->modify(is_numeric($deadtime) ? "+ ".floor($deadtime)." seconds" : $deadtime);
            $this->setDeadtime($allowAt);
        }

        // Generate token value
        $this->value = rtrim(str_replace(["+","/"], ["",""], base64_encode(random_bytes(16))), '=');

        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="tokens")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self
    {
        if ($this->user && $this->user != $user)
            $this->user->removeToken($this);

        if ($user)
            $user->addToken($this);

        $this->user = $user;
        return $this;
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $expireAt;

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
    public function getRemainingTimeStr(): string { return $this->getTranslator()->time($this->getRemainingTime()); }


    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $allowAt;

    public function getAllowAt(): ?\DateTimeInterface { return $this->allowAt; }
    public function setAllowAt(\DateTimeInterface $allowAt): self
    {
        if(!$this->getCreatedAt())
            $this->setCreatedAt(new \DateTime("now"));

        if ($allowAt < $this->getCreatedAt())
            $allowAt = $this->createdAt;

        $this->allowAt = $allowAt;
        return $this;
    }

    public function hasVeto():bool { return $this->isValid() && ($this->getAllowAt() == null ? false : new \DateTime("now") < $this->getAllowAt()); }
    public function getDeadtime():int { return $this->allowAt->getTimestamp() - time(); }
    public function getDeadtimeStr(): string { return $this->getTranslator()->time($this->getRemainingTime()); }
    public function setDeadtime(\DateTimeInterface $allowAt): self { return $this->setAllowAt($allowAt); }
    public function hasDeadtime():bool { return $this->getAllowAt() != $this->getCreatedAt(); }
    
    /**
     * @ORM\Column(type="boolean")
     */
    protected $isRevoked;

    public function revoke(): self { return $this->markAsRevoked(); }
    public function isRevoked(): bool { return $this->isRevoked; }
    public function markAsRevoked(): self
    {
        $this->isRevoked = true;
        
        if(($user = $this->getUser())) 
            $user->removeToken($this);

        return $this;
    }

}
