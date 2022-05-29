<?php

namespace Base\Entity\User;


use App\Entity\User;
use App\Entity\User\Group;
use Base\Model\IconizeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\User\PenaltyRepository;

/**
 * @ORM\Entity(repositoryClass=PenaltyRepository::class)
 */
class Penalty implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-exclamation-triangle"]; }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="penalties")
     */
    protected $uid;

    /**
     * @ORM\ManyToMany(targetEntity=Group::class, mappedBy="penalties")
     */
    protected $gid;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $type;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $duration;

    /**
     * @ORM\Column(type="text")
     */
    protected $extra;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    public function __construct()
    {
        $this->uid = new ArrayCollection();
        $this->gid = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDuration(): ?\DateTimeInterface
    {
        return $this->duration;
    }

    public function setDuration(?\DateTimeInterface $duration): self
    {
        $this->duration = $duration;

        return $this;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUid(): Collection
    {
        return $this->uid;
    }

    public function addUid(User $uid): self
    {
        if (!$this->uid->contains($uid)) {
            $this->uid[] = $uid;
            $uid->addPenalty($this);
        }

        return $this;
    }

    public function removeUid(User $uid): self
    {
        if ($this->uid->removeElement($uid)) {
            $uid->removePenalty($this);
        }

        return $this;
    }

    /**
     * @return Collection|Group[]
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
