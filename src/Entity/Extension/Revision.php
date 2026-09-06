<?php

namespace Base\Entity\Extension;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Extension\Abstract\AbstractExtension;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Extension\RevisionRepository;

use Base\Database\Attribute\Cache;

/**
 * One entry of an entity's modification history: what changed in a single
 * flush, as `entityData = {"<field>": [old, new]}`.
 *
 * NB the hash used to be `#[ORM\Column(type:"integer")] #[Hashify(random:true)]`,
 * which could not work in either direction: Hashify hands prePersist a hashed
 * STRING for an integer column, and its postPersist calls erasePlainMessage(),
 * which throws outright when no reference column is configured - so the very
 * first Revision ever persisted would have taken the flush down with it. It
 * went unnoticed because nothing ever wrote one. getHashShort() shows the
 * intent was a git-style short hash, so that is what it now is.
 */
#[ORM\Entity(repositoryClass:RevisionRepository::class)]
#[Cache(usage:"NONSTRICT_READ_WRITE", associations:"ALL")]
#[DiscriminatorEntry(value:"revision")]
class Revision extends AbstractExtension
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-sort-numeric-down"];
    }

    public function __construct()
    {
        $this->hash = bin2hex(random_bytes(20));
    }

    #[ORM\Column(type: "string", length: 40)]
    protected $hash;

    /**
     * @return mixed
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getHashShort()
    {
        return substr($this->hash, 0, 7);
    }

    /**
     * Field names this revision touched, with the locale prefix stripped off.
     *
     * @return string[]
     */
    public function getFields(): array
    {
        return array_keys($this->getEntityData());
    }

    /**
     * The value $field held BEFORE this revision - what a restore puts back.
     */
    public function getPreviousValue(string $field): mixed
    {
        return $this->getEntityData()[$field][0] ?? null;
    }

    public function getNextValue(string $field): mixed
    {
        return $this->getEntityData()[$field][1] ?? null;
    }

    public function supports(): bool
    {
        return true;
    }
}
