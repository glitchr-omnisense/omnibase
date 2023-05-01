<?php

namespace Base\Entity\Layout;

use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Service\Model\IconizeInterface;
use Base\Validator\Constraints as AssertBase;
use Base\Annotations\Annotation\Slugify;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\SettingRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=SettingRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 */
class Setting implements TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-tools"];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getLabel() ?? "";
    }

    /**
     * @param string $path
     * @param $value
     * @param $locale
     * @throws \Exception
     */
    public function __construct(string $path, $value = null, $locale = null)
    {
        $this->setLocked(false);
        $this->setBag(null);

        $this->setPath($path);
        $this->translate($locale)->setValue($value);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\Column(type="string", length=255)
     * @AssertBase\NotBlank(groups={"new", "edit"})
     * @Slugify(reference="translations.label", separator=".", keep={"_"})
     */
    protected $path;

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return $this
     */
    /**
     * @param string $path
     * @return $this
     */
    public function setPath(string $path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $locked;

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function getLocked(): bool
    {
        return $this->isLocked();
    }

    public function lock(): self
    {
        return $this->setLocked(true);
    }

    public function unlock(): self
    {
        return $this->setLocked(false);
    }

    /**
     * @param bool $locked
     * @return $this
     */
    /**
     * @param bool $locked
     * @return $this
     */
    public function setLocked(bool $locked)
    {
        $this->locked = $locked;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true, nullable=true)
     */
    protected $bag;

    public function getBag(): ?string
    {
        return $this->bag;
    }

    /**
     * @param string|null $bag
     * @return $this
     */
    /**
     * @param string|null $bag
     * @return $this
     */
    public function setBag(?string $bag)
    {
        $this->bag = $bag;
        return $this;
    }
}
