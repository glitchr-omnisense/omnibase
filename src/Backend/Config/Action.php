<?php

namespace Base\Backend\Config;

/**
 *
 */
class Action extends \EasyCorp\Bundle\EasyAdminBundle\Config\Action
{
    public const GOTO_PREV = 'prev';
    public const GOTO_SEE = 'see';
    public const GOTO_NEXT = 'next';
    public const SEPARATOR = 'separator';
    public const GROUP = 'group';
    public const GOTO = 'goto';

    /**
     * @return $this
     */
    /**
     * @return $this
     */
    public function renderAsTooltip()
    {
        $this->dto->addHtmlAttributes(['tooltip' => true]);

        return $this;
    }

    /**
     * @return $this
     */
    /**
     * @return $this
     */
    public function targetBlank()
    {
        $this->dto->addHtmlAttributes(['target' => "_blank"]);

        return $this;
    }

    /**
     * @return $this
     */
    /**
     * @return $this
     */
    public function displayAsSeparator()
    {
        $this->dto->setHtmlElement('separator');

        return $this;
    }

    /**
     * @return $this
     */
    /**
     * @return $this
     */
    public function displayAsDropdown()
    {
        $this->dto->setHtmlElement('dropdown');

        return $this;
    }
}
