<?php

namespace Base\Entity\User;

use App\Entity\User;
use App\Entity\User\Penalty;
use App\Entity\User\Permission;
use Base\Service\Model\IconizeInterface;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Base\Database\Annotation\Cache;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\GroupRepository;

/**
 * @ORM\Entity(repositoryClass=GroupRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 */
class Group implements IconizeInterface
{
    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-users"];
    }

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->permissions = new ArrayCollection();
        $this->penalties = new ArrayCollection();
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\Column(type="json")
     */
    protected $roles = [];

    public function getRoles(): array
    {
        return array_unique($this->roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    
    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="groups")
     */
    protected $members;
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): self
    {
        if (!$this->members->contains($member)) {
            $this->members[] = $member;
            $member->addGroup($this);
        }

        return $this;
    }

    public function removeMember(User $member): self
    {
        if ($this->members->removeElement($member)) {
            $member->removeGroup($this);
        }

        return $this;
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;
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
     * @ORM\ManyToMany(targetEntity=Permission::class, inversedBy="gid")
     */
    protected $permissions;

    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): self
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions[] = $permission;
        }

        return $this;
    }

    public function removePermission(Permission $permission): self
    {
        $this->permissions->removeElement($permission);

        return $this;
    }
    
    /**
     * @ORM\ManyToMany(targetEntity=Penalty::class, inversedBy="gid")
     */
    protected $penalties;

    public function getPenalties(): Collection
    {
        return $this->penalties;
    }

    public function addPenalty(Penalty $penalty): self
    {
        if (!$this->penalties->contains($penalty)) {
            $this->penalties[] = $penalty;
        }

        return $this;
    }

    public function removePenalty(Penalty $penalty): self
    {
        $this->penalties->removeElement($penalty);

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=7, nullable=true)
     */
    protected $color;

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $icon;

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
