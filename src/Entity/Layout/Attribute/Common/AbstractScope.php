<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Annotations\Annotation\Uploader;
use Base\Database\Annotation\DiscriminatorEntry;

use Base\Entity\Thread\Taxon;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Common\AbstractScopeRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AbstractScopeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry(value="abstract_scope")
 */
abstract class AbstractScope extends AbstractAttribute implements ScopeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-crosshairs"]; }
    public function contains(mixed $subject): bool
    {

        if(!$this->adapter) return true;

        $ret = false;
        foreach($this->getValue() as $value) {

            if(!$this->adapter->supports($value)) continue;
            $ret |= $this->adapter->contains($value, $subject);
            if($ret) break;
        }

        return $ret;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected $value;
    public function getValue()     { return $this->value; }
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }
}