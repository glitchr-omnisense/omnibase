<?php

namespace Base\Console\Task;

/** user:create's arguments and options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class UserCreateInput
{
    /**
     * @param list<string> $roles        USER, ADMIN, SUPERADMIN... (ROLE_ optional)
     * @param ?string      $password     its password (generated otherwise)
     * @param bool         $keepPassword not to be changed at the first sign-in
     * @param bool         $noEmail      not e-mailed its username and password
     */
    public function __construct(
        public string $username,
        public string $email,
        public array $roles = ['USER'],
        public ?string $password = null,
        public bool $keepPassword = false,
        public bool $noEmail = false,
    ) {
    }
}
