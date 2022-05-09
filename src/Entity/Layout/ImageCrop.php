<?php

namespace Base\Entity\Layout;

use Base\Annotations\Annotation\Slugify;
use Base\Entity\Layout\Image;
use Base\Model\UrlInterface;
use Base\Repository\Layout\ImageCropRepository;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ImageCropRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class ImageCrop implements ImageCropInterface, UrlInterface
{
    use BaseTrait;

    public function __toUrl(): ?string {

        $identifier = $this->getSlug() ?? $this->getWidth().":".$this->getHeight();
        $hashid = $this->getImageService()->getHashId($this->getImage()->getSource());

        return $this->getRouter()->generate("ux_crop", ["identifier" => $identifier, "hashid" => $hashid]);
    }

    public function __toString() {

        return $this->getLabel() ?? $this->getTranslator()->entity($this).($this->getId() ? " #".$this->getId() : null);
    }

    public function getRatio() { return $this->getWidth()/$this->getHeight(); }

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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $label;

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Slugify(unique=false, keep={":"})
     */
    protected $slug;

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPivotX() { return $this->width/2; }
    public function getPivotY() { return $this->height/2; }

    public function getData(): array
    {
        return [
            "x" => $this->getX(),
            "y" => $this->getY(),
            "width" => $this->getWidth(),
            "height" => $this->getHeight(),
            "scaleX" => $this->getScaleX(),
            "scaleY" => $this->getScaleY(),
            "rotate" => $this->getRotate(),
        ];
    }

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $x;
    public function getX(): ?int { return $this->x; }
    public function setX(int $x): self
    {
        $this->x = $x;
        return $this;
    }

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $y;
    public function getY(): ?int { return $this->y; }
    public function setY(int $y): self
    {
        $this->y = $y;
        return $this;
    }

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $width;
    public function getNaturalWidth(): ?int { return $this->getImage()->getSourceMeta()[0] ?? null; }
    public function getWidth(): ?int { return $this->width; }
    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $height;
    public function getNaturalHeight(): ?int { return $this->getImage()->getSourceMeta()[1] ?? null; }
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
