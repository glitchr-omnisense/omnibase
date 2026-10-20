<?php

namespace Tests\Base\Bridge\Doctrine\Fixtures\Services;

use Doctrine\ORM\Mapping as ORM;

/** An entity whose repository takes a cache pool beside the registry (RepositoryServicesTest). */
#[ORM\Entity(repositoryClass: CountedRepository::class)]
class Counted
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 1;
}
