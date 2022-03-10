<?php

namespace Base\Entity\Layout;

use Base\Entity\Layout\Image;
use Base\Repository\Layout\ImageCropRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CropRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class ImageCrop
{
    public function __construct() { }

    public function getPivotX() { return $this->left+$this->width/2; }
    public function getPivotY() { return $this->top+$this->height/2; }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(type="integer")
     */
    protected $top;
    public function getTop(): ?int { return $this->top; }
    public function setTop(int $top): self
    {
        $this->top = $top;

        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $left;
    public function getLeft(): ?int { return $this->left; }
    public function setLeft(int $left): self
    {
        $this->left = $left;

        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $width;
    public function getWidth(): ?int { return $this->width; }
    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $height;
    public function getHeight(): ?int { return $this->height; }
    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $scaleX;
    public function getScaleX(): ?float { return $this->scaleX; }
    public function setScaleX(float $scaleX): self
    {
        $this->scaleX = $scaleX;

        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $scaleY;
    public function getScaleY(): ?float { return $this->scaleY; }
    public function setScaleY(float $scaleY): self
    {
        $this->scaleY = $scaleY;

        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $angle;
    public function getAngle(): ?int { return $this->angle; }
    public function setAngle(int $angle): self
    {
        $this->angle = $angle;

        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=Image::class, inversedBy="crops")
     */
    protected $image;
    public function getImage(): ?Image { return $this->image; }
    public function setImage(?Image $image): self
    {
        $this->image = $image;

        return $this;
    }
}
