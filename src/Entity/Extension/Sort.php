<?php

namespace Base\Entity\Extension;

use App\Entity\User;
use Base\Annotations\Annotation\Hashify;

use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Extension\SortRepository;
use Base\Traits\BaseTrait;

/**
 * @ORM\Entity(repositoryClass=SortRepository::class)
 */
class Sort implements IconizeInterface
{
    use BaseTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-sort-alpha-down"]; } 

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId() { return $this->id; }

    /**
     * @ORM\OneToOne(targetEntity=User::class)
     */
    protected $impersonator;
    public function getImpersonator(): ?User { return $this->impersonator; }
    public function setImpersonator(?User $impersonator): self
    {
        $this->impersonator = $impersonator;
        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    protected $user;
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $entityId;
    public function getEntity() { return $this->createdAt; }
    public function setEntity(object $entity) 
    {
        $this->setEntityId($entity);
        $this->setEntityClass($entity);

        return $this;
    }

    public function getEntityId() { return $this->entityId; }
    protected function setEntityId(mixed $entityOrId) 
    {
        if($this->getService()->isEntity($entityOrId)) $this->entityId = $entityOrId->getId();
        else $this->entityId = $entityOrId;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $entityClass;
    public function getEntityClass() { return $this->createdAt; }
    protected function setEntityClass(object $entity) 
    {
        $this->entityClass = get_class($entity);
        return $this;
    }

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    protected $ordering;
    public function getColumns(): array { return array_keys($this->ordering); }
    public function getOrdering(): array { return $this->ordering; }
    protected function setOrdering(array $ordering)
    {
        $this->ordering = $ordering;
        return $this;
    }

    /**
     * @ORM\Column(type="datetime")
     * Timestamp(on="create")
     */
    protected $createdAt;
    public function getCreatedAt() { return $this->createdAt; }
}
