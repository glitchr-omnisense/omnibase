<?php

namespace Base\Entity\Layout;

use Base\Entity\Layout\Image;
use Base\Repository\Layout\ImageCropRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CropRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class ImageCrop implements ImageCropInterface
{
    public function __construct() { }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int { return $this->id; }

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
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $label;

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @ORM\Column(type="quadrant8")
     */
    protected $quadrant;
    public function getQuadrant(): ?string { return $this->quadrant; }
    public function setQuadrant(string $quadrant): self
    {
        $this->quadrant = $quadrant;
        return $this;
    }

    public function getPivotX() { return $this->x+$this->width/2; }
    public function getPivotY() { return $this->y+$this->height/2; }

    public function getCropper():array { dump($this); return []; }
    public function setCropper($cropper) { dump($cropper); exit(1); return $this; }

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $x;
    public function getX(): ?int { return $this->x; }
    public function setX(int $x): self
    {
        $this->x = $x;
        return $this;
    }

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $y;
    public function getY(): ?int { return $this->y; }
    public function setY(int $y): self
    {
        $this->y = $y;
        return $this;
    }
    
    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $width;
    public function getWidth(): ?int { return $this->width; }
    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $height;
    public function getHeight(): ?int { return $this->height; }
    public function setHeight(int $height): self
    {
        $this->height = $height;
        return $this;
    }

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $scaleX;
    public function getScaleX(): ?float { return $this->scaleX; }
    public function setScaleX(float $scaleX): self
    {
        $this->scaleX = $scaleX;
        return $this;
    }

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $scaleY;
    public function getScaleY(): ?float { return $this->scaleY; }
    public function setScaleY(float $scaleY): self
    {
        $this->scaleY = $scaleY;
        return $this;
    }

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $rotate;
    public function getRotate(): ?int { return $this->rotate; }
    public function setRotate(int $rotate): self
    {
        $this->rotate = $rotate;
        return $this;
    }
}
