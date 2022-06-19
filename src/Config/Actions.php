<?php

namespace Base\Config;

use function Symfony\Component\Translation\t;

class Actions extends \EasyCorp\Bundle\EasyAdminBundle\Config\Actions
{
    /**
     * The $pageName is needed because sometimes the same action has different config
     * depending on where it's displayed (to display an icon in 'detail' but not in 'index', etc.).
     */
    protected function createBuiltInAction(string $pageName, string $actionName): Action
    {
        $action = parent::createBuiltInAction($pageName, $actionName);
        if($action) return $action;

        if (Action::GOTO_PREV === $actionName) {
            return Action::new(Action::GOTO_PREV, t('action.goto_prev', domain: 'EasyAdminBundle'))
                ->setCssClass('action-'.Action::GOTO_PREV)
                ->addCssClass('btn btn-secondary action-save')
                ->displayAsButton()
                ->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => $actionName])
                ->linkToCrudAction(Action::EDIT);
        }

        if (Action::GOTO_NEXT === $actionName) {
            return Action::new(Action::GOTO_NEXT, t('action.goto_next', domain: 'EasyAdminBundle'))
                ->setCssClass('action-'.Action::GOTO_NEXT)
                ->addCssClass('btn btn-secondary action-save')
                ->displayAsButton()
                ->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => $actionName])
                ->linkToCrudAction(Action::EDIT);
        }

        throw new \InvalidArgumentException(sprintf('The "%s" action is not a built-in action, so you can\'t add or configure it via its name. Either refer to one of the built-in actions or create a custom action called "%s".', $actionName, $actionName));
    }
}
