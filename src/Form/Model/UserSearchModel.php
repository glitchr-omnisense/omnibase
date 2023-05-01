<?php

namespace Base\Form\Model;

use Base\Validator\Constraints as AssertBase;

/**
 * @AssertBase\NotBlank
 */
class UserSearchModel
{
    /**
     * @var ?string
     */
    public ?string $username;
}
