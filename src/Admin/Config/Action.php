<?php

namespace Base\Admin\Config;

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
    public function renderAsTooltip()
    {
        $this->dto->addHtmlAttributes(['tooltip' => true]);

        return $this;
    }

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
    public function displayAsSeparator()
    {
        \trigger_deprecation(
            'glitchr/base-bundle',
            '1.8.0',
            'The "%s()" method is deprecated, use displayAsButton() instead.',
            __METHOD__
        );
        // $this->dto->setHtmlElement('separator');
        $this->dto->setHtmlElement('button');

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
                \trigger_deprecation(
            'glitchr/base-bundle',
            '1.8.0',
            'The "%s()" method is deprecated, use displayAsButton() instead.',
            __METHOD__
        );

        // $this->dto->setHtmlElement('dropdown');
        $this->dto->setHtmlElement('button');

        return $this;
    }
}
