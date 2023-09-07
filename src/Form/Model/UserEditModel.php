<?php

namespace Base\Form\Model;

use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class UserEditModel
{
    /**
     * @var ?string
     */
    public /*?string*/ $avatar;

    /**
     * @var ?string
     */
    public /*?string*/ $username;

    /**
     * @var ?string
     */
    public /*?string*/ $email;
}
