<?php

namespace Base\Database;

final class Events
{
    public const resolveDiscriminator = 'resolveDiscriminator';
    public const preQuery = 'preQuery';
    public const onQuery  = 'onQuery';
    public const postQuery  = 'postQuery';
}