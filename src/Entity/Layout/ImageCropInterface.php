<?php

namespace Base\Entity\Layout;

interface ImageCropInterface
{
    public function getPivotX();

    public function getPivotY();

    public function getX(): ?int;

    public function setX(int $x): self;

    public function getY(): ?int;

    public function setY(int $y): self;

    public function getWidth(): ?int;

    public function setWidth(int $width): self;

    public function getHeight(): ?int;

    public function setHeight(int $height): self;

    public function getScaleX(): ?float;

    public function setScaleX(float $scaleX): self;

    public function getScaleY(): ?float;

    public function setScaleY(float $scaleY): self;

    public function getRotate(): ?int;

    public function setRotate(int $rotate);
}
