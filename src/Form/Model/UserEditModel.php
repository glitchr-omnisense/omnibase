<?php

namespace Base\Form\Model;

use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class UserEditModel
{
    /**
     * @var string
     */
    public $avatar;
    
    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $email;
}
