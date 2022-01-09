<?php

namespace Base\Entity\Thread;

use App\Entity\User;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;

use Base\Repository\ThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Validator\Constraints as Assert;
use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Timestamp;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\Hierarchify;
use Base\Enum\ThreadState;
use Base\Database\TranslationInterface;
use Base\Traits\BaseTrait;
use Base\Database\Traits\TranslationTrait;

use Doctrine\ORM\Mapping\DiscriminatorColumn;

/**
 * @ORM\Entity()
 */

class TagTranslation implements TranslationInterface
{
    use TranslationTrait;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $description;
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
