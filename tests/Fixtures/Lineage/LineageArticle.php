<?php

namespace Tests\Base\Fixtures\Lineage;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Mapping as ORM;

/** A thread of its own table (JOINED under Base\Entity\Thread) whose parent is a LineageDestination, as a site's article. */
#[ORM\Entity]
#[DiscriminatorEntry(value: "lineage_article")]
class LineageArticle extends \Base\Entity\Thread
{
}
