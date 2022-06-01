<?php

namespace Base\Entity\Layout;

use Base\Entity\Layout\ImageCrop;
use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\Uploader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Layout\ImageRepository;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Enum\Quadrant\Quadrant;
use Base\Model\IconizeInterface;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @ORM\Entity(repositoryClass=ImageRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @ORM\DiscriminatorColumn( name = "type", type = "string" )
 *     @DiscriminatorEntry
 */
class Image implements IconizeInterface
{
    use BaseTrait;

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $cropIdentifier = array_pop_key("crop", $routeParameters);
        $routeName = $cropIdentifier ? "ux_imageCrop" : "ux_image" ;

        $routeParameters = array_merge($routeParameters, [
            "hashid" => $this->getImageService()->obfuscate($this->getSource()),
            "identifier" => $cropIdentifier
        ]);

        $m1 = memory_get_usage();
        $this->getRouter()->generate($routeName, $routeParameters, $referenceType);
        $m2 = memory_get_usage();
        $this->getRouter()->generate($routeName, $routeParameters, $referenceType);
        $m3 = memory_get_usage();
        $this->getRouter()->generate($routeName, $routeParameters, $referenceType);
        $m4 = memory_get_usage();
        $this->getRouter()->generate($routeName, $routeParameters, $referenceType);
        $m5 = memory_get_usage();
        $this->getRouter()->generate($routeName, $routeParameters, $referenceType);
        $m6 = memory_get_usage();
        $this->getRouter()->generate($routeName, $routeParameters, $referenceType);
        $m7 = memory_get_usage();
        dump("---> " .$m1." ".$m2." ".$m3." ".$m4." ".$m5." ".$m6." ".$m7);

        exit(1);
    return $this->getRouter()->generate($routeName, $routeParameters, $referenceType);
    }

    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-images"]; }

    public function __toString() { return Uploader::getPublic($this, "source") ?? $this->getService()->getParameterBag("base.image.no_image") ?? ""; }
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
     * @AssertBase\File(max_size="5MB", groups={"new", "edit"})
     * @Uploader(storage="local.storage", max_size="5MB", mime_types={"image/gif", "image/png", "image/jpeg", "image/bmp", "image/webp"})
     */
    protected $source;
    public function getSource()     { return Uploader::getPublic($this, "source"); }
    public function getSourceFile() { return Uploader::get($this, "source"); }
    public function setSource($source): self
    {
        $this->source = $source;
        $this->sourceMeta = null;
        return $this;
    }

    private $sourceMeta;
    public function getSourceMeta(): array|null|false
    {
        $sourceFile = $this->getSourceFile();
        if($sourceFile === null) return null;

        $this->sourceMeta = $this->sourceMeta ?? getimagesize($sourceFile->getPathname());
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
