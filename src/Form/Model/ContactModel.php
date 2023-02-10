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
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $subject;

    /**
     * @var string
     */
    public $message;

    /**
     * @var array
     * @Uploader(storage="local.storage", max_size="5MB", mime_types={"image/*"})
     */
    public $attachments;
}
