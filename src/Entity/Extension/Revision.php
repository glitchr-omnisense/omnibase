<?php

namespace Base\Entity\Extension;

use App\Entity\User;
use Base\Annotations\Annotation\Hashify;

use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Extension\RevisionRepository;
use Base\Traits\BaseTrait;

/**
 * @ORM\Entity(repositoryClass=RevisionRepository::class)
 */
class Revision implements IconizeInterface
{
    use BaseTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-sort-numeric-down"]; } 

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId() { return $this->id; }

    /**
     * @ORM\Column(type="integer")
     * @Hashify(hash_algorithm="sha512")
     */
    protected $hash;
    public function getHash() { return $this->hash; }
    public function getHashShort() { return substr($this->hash, 0, 7); }

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
    protected $entityData;
    public function getEntityData(): array { return $this->entityData; }
    protected function setEntityData(array $entityData)
    {
        $this->entityData = $entityData;
        return $this;
    }

    /**
     * @ORM\Column(type="datetime")
     * Timestamp(on="create")
     */
    protected $createdAt;
    public function getCreatedAt() { return $this->createdAt; }
}
