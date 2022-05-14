<?php

namespace Base\Entity\Layout;

use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Model\IconizeInterface;
use Base\Validator\Constraints as AssertBase;
use Base\Annotations\Annotation\Slugify;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\SettingRepository;

/**
 * @ORM\Entity(repositoryClass=SettingRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class Setting implements TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-tools"]; }

    public function __toString() { return $this->getPath() ?? ""; }
    public function __construct(string $path, $value = null, $locale = null)
    {
        $this->setLocked(false);
        $this->setBag(null);
        
        $this->setPath($path);
        if($value !== null)
            $this->translate($locale)->setValue($value);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(type="string", length=255)
     * @AssertBase\NotBlank(groups={"new", "edit"})
     * @Slugify(reference="translations.label", separator=".", keep={"_"})
     */
    protected $path;

    public function getPath(): string { return $this->path; }
    public function setPath(string $path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $locked;
    public function isLocked() : bool { return $this->locked; }
    public function getLocked(): bool { return $this->isLocked(); }

    public function lock(): self { return $this->setLocked(true); }
    public function unlock(): self { return $this->setLocked(false); }
    public function setLocked(bool $locked)
    {
        $this->locked = $locked;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true, nullable=true)
     */
    protected $bag;

    public function getBag(): ?string { return $this->bag; }
    public function setBag(?string $bag)
    {
        $this->bag = $bag;
        return $this;
    }
}
