<?php

namespace Base\Screen\Account;

use Base\Screen\Screen;

/**
 * A POST a page sends (plan "omnibase agnostique", C5): the address it goes
 * to and the token of its intent (Omnibridge\Contract\Tokens), which the action
 * checks. Only on a confidential screen (Base\Screen\Confidential): its
 * answers are stored by no cache.
 */
final readonly class Action implements Screen
{
    public function __construct(
        public string $url,
        public string $token,
    ) {
    }

    public function toArray(): array
    {
        return ['url' => $this->url, 'token' => $this->token];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
