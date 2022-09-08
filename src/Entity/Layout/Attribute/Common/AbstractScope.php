<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Database\Annotation\DiscriminatorEntry;

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
}