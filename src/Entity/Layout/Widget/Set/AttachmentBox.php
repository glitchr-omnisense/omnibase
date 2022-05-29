<?php

namespace Base\Entity\Layout\Widget\Set;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Set\SetInterface;
use Base\Entity\Layout\Widget\Attachment;
use Base\Model\IconizeInterface;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\AttachmentBoxRepository;

/**
 * @ORM\Entity(repositoryClass=AttachmentBoxRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry
 */
class AttachmentBox extends Widget implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-boxes"]; }

    public function __construct(string $title, array $attachments = [])
    {
        $this->attachments = new ArrayCollection($attachments);
        parent::__construct($title);
    }

    /**
     * @ORM\ManyToMany(targetEntity=Attachment::class, orphanRemoval=true, cascade={"persist"})
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
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