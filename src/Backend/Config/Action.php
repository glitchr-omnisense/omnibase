<?php

namespace Base\Backend\Config;

class Action extends \EasyCorp\Bundle\EasyAdminBundle\Config\Action
{
    public const GOTO_PREV = 'prev';
    public const GOTO_NEXT = 'next';
    public const SEPARATOR = 'separator';
    public const GROUP = 'group';

    public function displayAsTooltip()
    {
        $this->dto->setHtmlElement('tooltip');

        return $this;
    }

    public function displayAsSeparator()
    {
        $this->dto->setHtmlElement('separator');

        return $this;
    }

    public function displayAsDropdown()
    {
        $this->dto->setHtmlElement('dropdown');

        return $this;
    }
}
