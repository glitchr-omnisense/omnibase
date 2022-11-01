<?php

namespace Base\Entity\Layout;

use Base\Annotations\Annotation\Slugify;
use Base\Entity\Layout\Image;
use Base\Service\Model\LinkableInterface;
use Base\Traits\BaseTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\ImageCropRepository;
use Base\Service\Model\SaltInterface;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=ImageCropRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 */
class ImageCrop implements LinkableInterface, SaltInterface
{
    use BaseTrait;

    public function getSalt(): string { return $this->getImage()->getSalt()."_".md5(serialize($this->getData())); }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $routeName = array_key_exists("extension", $routeParameters) ? "ux_imageExtension" : "ux_image";
        $routeParameters = array_filter($routeParameters);
        $config = [
            "reference_type" => $referenceType,
            "identifier" => $this->getSlug() ?? $this->getWidth().":".$this->getHeight(), 
            "salt" => $this->getSalt()
        ];

        return $this->getImageService()->generate($routeName, $routeParameters, $this->getImage()->getSource(),  $config);
    }

    public function __toString() {

        return $this->getLabel() ?? $this->getTranslator()->transEntity($this).($this->getId() ? " #".$this->getId() : null);
    }

    public function getRatio() { return $this->getWidth0()/$this->getHeight0(); }
    public function isNormalized() // New coordinate system is using normalized values
    {
        if($this->x0      > 1) return false;
        if($this->y0      > 1) return false;
        if($this->width0  > 1) return false;
        if($this->height0 > 1) return false;

        return true;
    }

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
    public function getX (?int $width = null): ?int { return $this->isNormalized() ? $this->x0 * ($width ?? $this->getNaturalWidth()) : $this->x0; }
    public function getX0(): ?float { return $this->x0; }
    public function setX0(float $x0): self
    {
        $this->x0 = min(1, max(0, $x0));
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $y0;
    public function getY (?int $height = null): ?int { return $this->isNormalized() ? $this->y0 * ($height ?? $this->getNaturalHeight()) : $this->y0; }
    public function getY0(): ?float { return $this->y0; }
    public function setY0(float $y0): self
    {
        $this->y0 = min(1, max(0, $y0));
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $xP;
    public function getPivotX (?int $width = null) { return $this->isNormalized() ? $this->xP * ($width ?? $this->getNaturalWidth()) : $this->xP; }
    public function getXp():?float { return $this->xP; }
    public function setXp(float $xP): self
    {
        $this->xP = $xP;
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $yP;
    public function getPivotY (?int $height = null) { return $this->isNormalized() ? $this->yP * ($height ?? $this->getNaturalHeight()) : $this->xP; }
    public function getYp():?float { return $this->yP; }
    public function setYp(float $yP): self
    {
        $this->yP = $yP;
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $width0;
    public function getNaturalWidth(): ?int { return $this->getImage() ? $this->getImage()->getNaturalWidth() : 0; }
    public function getWidth (?int $width = null): ?int { return $this->isNormalized() ? $this->width0 * ($width ?? $this->getNaturalWidth()) : $this->width0; }
    public function getWidth0(): ?float { return $this->width0; }
    public function setWidth0(float $width0): self
    {
        $this->width0 = min(1, max(0, $width0));
        return $this;
    }

    /**
     * @ORM\Column(type="float")
     */
    protected $height0;
    public function getNaturalHeight(): ?int { return $this->getImage() ? $this->getImage()->getNaturalHeight() : 0; }
    public function getHeight (?int $height = null): ?int{ return $this->isNormalized() ? $this->height0 * ($height ?? $this->getNaturalHeight()) : $this->height0; }
    public function getHeight0(): ?float { return $this->height0; }
    public function setHeight0(float $height0): self
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
