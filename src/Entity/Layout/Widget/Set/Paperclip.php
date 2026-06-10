<?php

namespace Base\Entity\Layout\Widget\Set;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Database\Attribute\OrderColumn;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Attachment;
use Base\Service\Model\IconizeInterface;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\Set\PaperclipRepository;

use Base\Database\Attribute\Cache;

#[ORM\Entity(repositoryClass:PaperclipRepository::class)]
#[Cache(usage:"NONSTRICT_READ_WRITE", associations:"ALL")]
#[DiscriminatorEntry]
class Paperclip extends Widget implements IconizeInterface
{
    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-paperclip"];
    }

    public function __construct(?string $title = null, array $attachments = [])
    {
        $this->attachments = new ArrayCollection($attachments);
        parent::__construct($title);
    }

    #[ORM\ManyToMany(targetEntity:Attachment::class, orphanRemoval:true, cascade:["persist"])]
    #[OrderColumn(orderBy:"attachmentPositions")]
    protected $attachments;
    protected $attachmentPositions;
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }
    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments[] = $attachment;
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        $this->attachments->removeElement($attachment);
        return $this;
    }
}
