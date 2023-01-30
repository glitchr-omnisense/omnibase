<?php

namespace Base\Entity\Layout;

use Base\Entity\Layout\ImageCrop;
use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\Uploader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Enum\Quadrant\Quadrant;
use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Base\Service\Model\IconizeInterface;
use Base\Traits\BaseTrait;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Base\Database\Annotation\Cache;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Layout\ImageRepository;
use Base\Enum\Quadrant\Quadrant8;
use Base\Service\Model\SaltInterface;

/**
 * @ORM\Entity(repositoryClass=ImageRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @ORM\DiscriminatorColumn( name = "type", type = "string" )
 *     @DiscriminatorEntry
 */
class Image implements IconizeInterface, ImageInterface, SaltInterface
{
    use BaseTrait;

    public function getSalt(): string { return md5(serialize($this->getQuadrant())); }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $filters = array_pop_key("filters", $routeParameters) ?? [];
        if($this->getSource() === null) return null;

        $thumbnail  = array_pop_key("thumbnail", $routeParameters);
        $identifier = array_pop_key("crop", $routeParameters);
        if(is_array($thumbnail)) {

            $filters[] = new ThumbnailFilter($thumbnail[0] ?? null, $thumbnail[1] ?? null);
            $identifier ??= implode("x", array_slice($thumbnail, 0, 2)); // Set cropper using thumbnail information if not cropper not defined
        }

        if (array_key_exists("extension", $routeParameters)) {
            if($routeParameters["extension"] === true) $routeParameters["extension"] = first($this->getImageService()->getExtensions($this->getSource()));
            else if($routeParameters["extension"] === false) array_pop_key("extension", $routeParameters);
        }

        $routeName = (array_key_exists("extension", $routeParameters) ? "ux_imageExtension"     : "ux_image");
        $routeParameters = array_merge($routeParameters, [
            "data" => $this->getImageService()->obfuscate($this->getSource(), [
                "identifier" => is_array($identifier) ? implode("x", $identifier) : $identifier,
                "salt"       => $this->getSalt()
            ], $filters),
        ]);

        return $this->getRouter()->generate($routeName, $routeParameters, $referenceType);
    }

    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-images"]; }

    public function __toString() { return Uploader::getPublic($this, "source") ?? $this->getService()->getParameterBag("base.images.no_image") ?? ""; }
    public function __construct($src = null)
    {
        $this->crops = new ArrayCollection();
        $this->setSource($src);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(type="text")
     * @Uploader(storage="local.storage", max_size="32MB", mime_types={"image/gif", "image/png", "image/jpeg", "image/bmp", "image/webp"})
     */
    protected $source;
    public function getSource()     { return Uploader::getPublic($this, "source"); }
    public function getSourceFile() { return Uploader::get($this, "source"); }
    public function setSource($source): static
    {
        $this->source = $source;
        $this->sourceMeta = null;
        return $this;
    }

    private $sourceMeta;
    public function getNaturalWidth(): ?int { return $this->getSourceMeta()["width"] ?? 0; }
    public function getNaturalHeight(): ?int { return $this->getSourceMeta()["height"] ?? 0; }
    public function getSourceMeta(): array|null|false
    {
        $sourceFile = $this->getSourceFile();
        if($sourceFile === null) return null;

        if(empty($this->sourceMeta)) {

            $imagesize = getimagesize($sourceFile->getPathname());
            $this->sourceMeta = [
                "width" => $imagesize[0],
                "height" => $imagesize[1],
                "type" => $imagesize[2],
                "bits" => $imagesize["bits"],
                "channels" => $imagesize["channels"] ?? null,
                "mime" => $imagesize["mime"],
                "orientation" => getimageorientation($sourceFile->getPathname()),
            ];
        }

        return $this->sourceMeta;
    }

    public function get()     { return $this->getSource(); }
    public function getFile() { return $this->getSourceFile(); }
    public function set($source): self { return $this->setSource($source); }

    /**
     * @ORM\Column(type="quadrant8")
     */
    protected $quadrant = Quadrant::O;
    public function getQuadrant(): string { return $this->quadrant; }
    public function getQuadrantPosition(): string { return Quadrant8::getPosition($this->quadrant); }
    public function setQuadrant(string $quadrant): self
    {
        $this->quadrant = $quadrant;
        return $this;
    }

    /**
     * @ORM\OneToMany(targetEntity=ImageCrop::class, mappedBy="image", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $crops;
    public function getCrops(): Collection { return $this->crops; }
    public function addCrop(ImageCrop $crop): self
    {
        if (!$this->crops->contains($crop)) {
            $this->crops[] = $crop;
            $crop->setImage($this);
        }

        return $this;
    }

    public function removeCrop(ImageCrop $crop): self
    {
        if ($this->crops->removeElement($crop)) {
            // set the owning side to null (unless already changed)
            if ($crop->getImage() === $this) {
                $crop->setImage(null);
            }
        }

        return $this;
    }
}
