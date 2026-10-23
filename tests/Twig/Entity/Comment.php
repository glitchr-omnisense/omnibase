<?php

namespace Tests\Base\Twig\Entity;

/** A record whose screen's edit route exists (FrameworkFreeExtensionsTest: app.admin.crudify()). */
final class Comment
{
    public function __construct(private ?int $id = null)
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
