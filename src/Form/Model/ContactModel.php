<?php

namespace Base\Form\Model;

use Base\Annotations\Annotation\Uploader;
use Base\Form\Common\AbstractModel;
use Base\Notifier\Recipient\Recipient;
use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class ContactModel extends AbstractModel
{
    public function getRecipient(): ?Recipient
    {
        return new Recipient(mailformat([], $this->name, $this->email));
    }

    /**
     * @var ?string
     */
    public ?string $name;

    /**
     * @var ?string
     */
    public ?string $email;

    /**
     * @var ?string
     */
    public ?string $subject;

    /**
     * @var ?string
     */
    public ?string $message;

    /**
     * @var array
     * @Uploader(storage="local.storage", max_size="5MB", mime_types={"image/*"})
     */
    public array $attachments;
}
