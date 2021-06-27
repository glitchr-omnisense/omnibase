<?php

namespace Base\Form\Data\Thread;

use Base\Entity\Thread;

use Symfony\Component\Validator\Constraints as Assert;
use Base\Validator\Constraints as AssertBase;


class SearchData
{
    /**
     * @var string
     */
    public string $title;
    /**
     * @var string
     */
    public string $excerpt;

    /**
     * @Assert\NotBlank()
     * @var string
     */
    public string $content;

    /**
     * @var Thread
     */
    public Thread $parent;
}
