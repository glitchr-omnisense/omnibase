<?php

namespace Base\Model;

use App\Entity\User;
use Base\Enum\SpamScore;

interface SpamProtectionInterface
{
    public function getAuthor(): ?User;
 
    public function getSpamText(): ?string;
    public function getSpamDate(): \DateTime;
    public function getSpamCallback(SpamScore $score);
}