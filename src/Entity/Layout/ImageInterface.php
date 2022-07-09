<?php

namespace Base\Entity\Layout;

interface ImageInterface
{
    public function getSource();
    public function getSourceFile();
    public function setSource($source): static;

    public function getNaturalWidth(): ?int;
    public function getNaturalHeight(): ?int;
}
