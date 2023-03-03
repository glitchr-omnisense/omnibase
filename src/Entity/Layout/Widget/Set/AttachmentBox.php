<?php

namespace Base\Entity\Layout\Widget\Set;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Annotation\OrderColumn;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Attachment;
use Base\Service\Model\IconizeInterface;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\Set\AttachmentBoxRepository;

use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AttachmentBoxRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry
 */
class AttachmentBox extends Widget implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-boxes"]; }

    public function __construct(?string $title = null, array $attachments = [])
    {
        $this->attachments = new ArrayCollection($attachments);
        parent::__construct($title);
    }

    /**
     * @ORM\ManyToMany(targetEntity=Attachment::class, orphanRemoval=true, cascade={"persist"})
     * @OrderColumn
     */
    protected $attachments;
    public function getAttachments(): Collection { return $this->attachments; }
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