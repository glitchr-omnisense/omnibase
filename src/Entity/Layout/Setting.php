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
    public function getOneOrNullValue()
    {
        $value = $this->getValue();
        if(is_array($value)) return $value[0] ?? null;
        return $value;
    }

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-tools"]; }

    public function __toString() { return $this->getPath() ?? ""; }
    public function __construct(string $path, $value = null, $locale = null)
    {
        $this->setPath($path);
        $this->setSecure(false);

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
     * @ORM\Column(type="string", length=255, unique=true)
     * @AssertBase\NotBlank(groups={"new", "edit"})
     * @Slugify(reference="translations.title", separator=".", exception="_")
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
    protected $secure;

    public function isSecure(): bool { return $this->secure; }
    public function setSecure(bool $secure)
    {
        $this->secure = $secure;
        return $this;
    }
}
