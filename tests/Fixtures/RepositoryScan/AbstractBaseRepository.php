<?php

namespace Tests\Base\Fixtures\RepositoryScan;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

/** A base the bundle's repositories extend: aliased, never registered. */
abstract class AbstractBaseRepository extends ServiceEntityRepository
{
}
