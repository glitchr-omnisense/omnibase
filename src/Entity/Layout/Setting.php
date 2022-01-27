<?php

namespace Base\Entity\Layout;

use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\SettingRepository;

/**
 * @ORM\Entity(repositoryClass=SettingRepository::class)
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
     */
    protected $path;

    public function getPath(): string { return $this->path; }
    public function setPath(string $path)
    {
        $this->path = $path;
        return $this;
    }

}
