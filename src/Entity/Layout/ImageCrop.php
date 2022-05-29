<?php

namespace Base\Entity\Layout;

use Base\Annotations\Annotation\Slugify;
use Base\Entity\Layout\Image;
use Base\Model\LinkableInterface;
use Base\Repository\Layout\ImageCropRepository;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @ORM\Entity(repositoryClass=ImageCropRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class ImageCrop implements LinkableInterface
{
    use BaseTrait;

    public function __toLink(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $routeParameters = [
            "identifier" => $this->getSlug() ?? $this->getWidth().":".$this->getHeight(),
            "hashid"     => $this->getImageService()->obfuscate($this->getImage()->getSource())
        ];

        return $this->getRouter()->generate("ux_crop", $routeParameters, $referenceType);
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
    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Slugify(reference="label", unique=false, keep={":"}, nullable=true, sync=true)
     */
    protected $slug;

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function __construct(?Image $image = null) {

        $this->setImage($image);

        $this->x0      = 0;
        $this->y0      = 0;
        $this->width0  = 1;
        $this->height0 = 1;

        $this->xP      = 0.5;
        $this->yP      = 0.5;

        $this->scaleX  = 1;
        $this->scaleY  = 1;
        $this->rotate  = 0;
    }

    public function getData(): array
    {
        return [
            "x0"       => $this->getX0(),
            "y0"       => $this->getY0(),
            "width0"   => $this->getWidth0(),
            "height0"  => $this->getHeight0(),
            "scaleX"  => $this->getScaleX(),
            "scaleY"  => $this->getScaleY(),
            "rotate"  => $this->getRotate(),
            "xP"      => $this->getScaleY(),
            "yP"      => $this->getRotate(),
        ];
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $x0;
    public function getX (): ?int { return $this->x0;}// * $this->getNaturalWidth(); }
    public function getX0(): ?int { return $this->x0; }
    public function setX0(int $x0): self
    {
        $this->x0 = min(1, max(0, $x0));
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $y0;
    public function getY (): ?int { return $this->y0;}// * $this->getNaturalHeight(); }
    public function getY0(): ?int { return $this->y0; }
    public function setY0(int $y0): self
    {
        $this->y0 = min(1, max(0, $y0));
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $xP;
    public function getPivotX () { return $this->xP;}// * $this->getNaturalWidth();  }
    public function getXp() { return $this->xP; }
    public function setXp(int $xP): self
    {
        $this->xP = $xP;
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $yP;
    public function getPivotY () { return $this->yP;}// * $this->getNaturalHeight(); }
    public function getYp() { return $this->yP; }
    public function setYp(int $yP): self
    {
        $this->yP = $yP;
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $width0;
    public function getNaturalWidth(): ?int { return $this->getImage() ? $this->getImage()->getSourceMeta()[0] ?? 0 : 0; }
    public function getWidth (): ?int { return $this->width0;}// * $this->getNaturalWidth(); }
    public function getWidth0(): ?int { return $this->width0; }
    public function setWidth0(int $width0): self
    {
        $this->width0 = min(1, max(0, $width0));
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $height0;
    public function getNaturalHeight(): ?int { return $this->getImage() ? $this->getImage()->getSourceMeta()[1] ?? 0 : 0; }
    public function getHeight (): ?int { return $this->height0;}// * $this->getNaturalHeight(); }
    public function getHeight0(): ?int { return $this->height0; }
    public function setHeight0(int $height0): self
    {
        $this->height0 = min(1, max(0, $height0));
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
    protected $rotate;
    public function getRotate(): ?int { return $this->rotate; }
    public function setRotate(int $rotate): self
    {
        $this->rotate = mod($rotate,360);
        return $this;
    }
}
