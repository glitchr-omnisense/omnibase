<?php

namespace Base\Entity\User;

use App\Entity\User;
use App\Entity\User\Group;
use Base\Service\Model\IconizeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\User\PermissionRepository;

/**
 * @ORM\Entity(repositoryClass=PermissionRepository::class)
 */
class Permission implements IconizeInterface
{
    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-lock"];
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $tag;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="permissions")
     */
    protected $uid;

    /**
     * @ORM\ManyToMany(targetEntity=Group::class, mappedBy="permissions")
     */
    protected $gid;

    /**
     * @ORM\Column(type="json")
     */
    protected $empower = [];

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $icon;

    /**
     * @ORM\Column(type="text")
     */
    protected $description;

    public function __construct()
    {
        $this->uid = new ArrayCollection();
        $this->gid = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @see GroupInterface
     */
    public function getEmpoweredRoles(): array
    {
        return array_unique($this->empower);
    }

    public function setEmpoweredRoles(array $empower): self
    {
        $this->empower = $empower;

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
            $uid->addPermission($this);
        }

        return $this;
    }

    public function removeUid(User $uid): self
    {
        if ($this->uid->removeElement($uid)) {
            $uid->removePermission($this);
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
            $gid->addPermission($this);
        }

        return $this;
    }

    public function removeGid(Group $gid): self
    {
        if ($this->gid->removeElement($gid)) {
            $gid->removePermission($this);
        }

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }
}
