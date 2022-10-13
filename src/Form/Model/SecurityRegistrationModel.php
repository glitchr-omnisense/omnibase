<?php

namespace Base\Form\Model;

use Base\Form\Common\AbstractModel;
use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class SecurityRegistrationModel extends AbstractModel
{
    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $password;
    
    /**
     * @var bool
     */
    public $agreeTerms;

}
