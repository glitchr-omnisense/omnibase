<?php

namespace Base\Entity\Extension\Abstract;

use Base\Annotations\Annotation\Blameable;
use Base\Annotations\Annotation\Timestamp;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\User;
use Base\Service\Model\IconizeInterface;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AbstractExtensionRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */

abstract class AbstractExtension implements AbstractExtensionInterface, IconizeInterface
{
    use BaseTrait;

    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-external-link"]; }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId() { return $this->id; }

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @Blameable(on={"create", "update"}, impersonator=true)
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $impersonator;
    public function getImpersonator(): ?User { return $this->impersonator; }

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Blameable(on={"create", "update"})
     */
    protected $initiator;
    public function getInitiator(): ?User { return $this->initiator; }

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $entityId;
    public function getEntityId() { return $this->entityId; }
    public function setEntityId(mixed $entityOrId)
    {
        if($this->getService()->isEntity($entityOrId)) $this->entityId = $entityOrId->getId();
        else $this->entityId = $entityOrId;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $entityClass;
    public function getEntityClass() { return $this->entityClass; }
    public function setEntityClass(object|string $entity)
    {
        $this->entityClass = is_object($entity) ? get_class($entity) : $entity;
        return $this;
    }

    /**
     * @ORM\Column(type="entity_action")
     */
    protected $action;
    public function getAction() { return $this->action; }
    public function setAction(string $action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    protected $entityData = [];
    public function isEmpty() { return empty($this->getEntityData()); }
    public function getEntityData(): array { return $this->entityData; }
    public function setEntityData(array $entityData)
    {
        $this->entityData = $entityData;
        return $this;
    }

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on="create")
     */
    protected $createdAt;
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
