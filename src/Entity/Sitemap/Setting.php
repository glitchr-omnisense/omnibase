<?php

namespace Base\Entity\Sitemap;

use App\Entity\User;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;
use Base\Repository\Sitemap\SettingRepository;
use Base\Repository\ThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Validator\Constraints as Assert;
use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Timestamp;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\EntityHierarchy;
use Base\Annotations\Annotation\Uploader;
use Base\Enum\ThreadState;
use Base\Database\TranslatableInterface;
use Base\Traits\BaseTrait;
use Base\Traits\EntityHierarchyTrait;
use Base\Database\Traits\TranslatableTrait;
use Countable;

/**
 * @ORM\Entity(repositoryClass=SettingRepository::class)
 */
class Setting implements TranslatableInterface
{
    use TranslatableTrait;
    public function getLabel(): ?string { return $this->translate()->getLabel();   }
    public function setLabel(?string $label) {
        $this->translate()->setLabel($label);  
        return $this;
    }

    public function getHelp(): ?string { return $this->translate()->getHelp();   }
    public function setHelp(?string $help) {
        $this->translate()->setHelp($help);  
        return $this; 
    }
    
    public function getValue() { return $this->translate()->getValue(); }
    public function setValue($value) {
        $this->translate()->setValue($value);  
        return $this; 
    }

    public function getOneOrNullValue()
    {
        $value = $this->getValue();
        if(is_array($value)) return $value[0] ?? null;
        return $value;
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
    protected $name;

    public function getName(): string { return $this->name; }
    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    public function __construct(string $name, $value)
    {
        $this->setName($name);
        $this->setValue($value);
    }

    public function __toString() { return $this->getValue() ?? ""; }
}
