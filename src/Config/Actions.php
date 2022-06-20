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

        dump($actionName);
        exit(1);
        return parent::createBuiltInAction($pageName, $actionName);
    }

    // protected function doAddAction(string $pageName, Action|string $actionNameOrObject, bool $isBatchAction = false)
    // {
    //     $actionName = \is_string($actionNameOrObject) ? $actionNameOrObject : (string) $actionNameOrObject;
    //     $action = \is_string($actionNameOrObject) ? $this->createBuiltInAction($pageName, $actionNameOrObject) : $actionNameOrObject;

    //     if (null !== $this->dto->getAction($pageName, $actionName)) {
    //         throw new \InvalidArgumentException(sprintf('The "%s" action already exists in the "%s" page, so you can\'t add it again. Instead, you can use the "updateAction()" method to update any options of an existing action.', $actionName, $pageName));
    //     }

    //     $actionDto = $action->getAsDto();
    //     if ($isBatchAction) {
    //         $actionDto->setType(Action::TYPE_BATCH);
    //     }

    //     if (Crud::PAGE_INDEX === $pageName && Action::DELETE === $actionName) {
    //         $this->dto->prependAction($pageName, $actionDto);
    //     } else {
    //         $this->dto->appendAction($pageName, $actionDto);
    //     }

    //     return $this;
    // }
}
