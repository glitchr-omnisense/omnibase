<?php

namespace Tests\Base\Fixtures\Lineage;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Mapping as ORM;

/** A thread of its own table (JOINED under Base\Entity\Thread): the parent of a ParentedArticle, as a site's destination. */
#[ORM\Entity]
#[DiscriminatorEntry(value: "lineage_destination")]
class LineageDestination extends \Base\Entity\Thread
{
}
