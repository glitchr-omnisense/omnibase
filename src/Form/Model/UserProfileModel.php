<?php

namespace Base\Form\Model;

use Base\Form\Common\AbstractModel;
use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class UserProfileModel extends AbstractModel
{
    /**
     * @var ?string
     */
    public ?string $email;

    /**
     * @var ?string
     */
    public ?string $plainPassword;

    /**
     * @var ?string
     */
    public ?string $avatar;
}
