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
        if (Action::BATCH_DELETE === $actionName) {
            return Action::new(Action::BATCH_DELETE, t('action.delete', domain: 'EasyAdminBundle'), null)
                ->linkToCrudAction(Action::BATCH_DELETE)
                ->setCssClass('action-'.Action::BATCH_DELETE)
                ->addCssClass('btn btn-danger pr-0');
        }

        if (Action::NEW === $actionName) {
            return Action::new(Action::NEW, t('action.new', domain: 'EasyAdminBundle'), null)
                ->createAsGlobalAction()
                ->linkToCrudAction(Action::NEW)
                ->setCssClass('action-'.Action::NEW)
                ->addCssClass('btn btn-primary');
        }

        if (Action::EDIT === $actionName) {
            return Action::new(Action::EDIT, t('action.edit', domain: 'EasyAdminBundle'), null)
                ->linkToCrudAction(Action::EDIT)
                ->setCssClass('action-'.Action::EDIT)
                ->addCssClass(Crud::PAGE_DETAIL === $pageName ? 'btn btn-primary' : '');
        }

        if (Action::DETAIL === $actionName) {
            return Action::new(Action::DETAIL, t('action.detail', domain: 'EasyAdminBundle'))
                ->linkToCrudAction(Action::DETAIL)
                ->setCssClass('action-'.Action::DETAIL)
                ->addCssClass(Crud::PAGE_EDIT === $pageName ? 'btn btn-secondary' : '');
        }

        if (Action::INDEX === $actionName) {
            return Action::new(Action::INDEX, t('action.index', domain: 'EasyAdminBundle'))
                ->linkToCrudAction(Action::INDEX)
                ->setCssClass('action-'.Action::INDEX)
                ->addCssClass(\in_array($pageName, [Crud::PAGE_DETAIL, Crud::PAGE_EDIT, Crud::PAGE_NEW], true) ? 'btn btn-secondary' : '');
        }

        if (Action::DELETE === $actionName) {
            $cssClass = \in_array($pageName, [Crud::PAGE_DETAIL, Crud::PAGE_EDIT], true) ? 'btn btn-secondary pr-0 text-danger' : 'text-danger';

            return Action::new(Action::DELETE, t('action.delete', domain: 'EasyAdminBundle'), Crud::PAGE_INDEX === $pageName ? null : 'fa fa-fw fa-trash-o')
                ->linkToCrudAction(Action::DELETE)
                ->setCssClass('action-'.Action::DELETE)
                ->addCssClass($cssClass);
        }

        if (Action::SAVE_AND_RETURN === $actionName) {
            return Action::new(Action::SAVE_AND_RETURN, t(Crud::PAGE_EDIT === $pageName ? 'action.save' : 'action.create', domain: 'EasyAdminBundle'))
                ->setCssClass('action-'.Action::SAVE_AND_RETURN)
                ->addCssClass('btn btn-primary action-save')
                ->displayAsButton()
                ->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => $actionName])
                ->linkToCrudAction(Crud::PAGE_EDIT === $pageName ? Action::EDIT : Action::NEW);
        }

        if (Action::SAVE_AND_CONTINUE === $actionName) {
            return Action::new(Action::SAVE_AND_CONTINUE, t(Crud::PAGE_EDIT === $pageName ? 'action.save_and_continue' : 'action.create_and_continue', domain: 'EasyAdminBundle'), 'far fa-edit')
                ->setCssClass('action-'.Action::SAVE_AND_CONTINUE)
                ->addCssClass('btn btn-secondary action-save')
                ->displayAsButton()
                ->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => $actionName])
                ->linkToCrudAction(Crud::PAGE_EDIT === $pageName ? Action::EDIT : Action::NEW);
        }

        if (Action::SAVE_AND_ADD_ANOTHER === $actionName) {
            return Action::new(Action::SAVE_AND_ADD_ANOTHER, t('action.create_and_add_another', domain: 'EasyAdminBundle'))
                ->setCssClass('action-'.Action::SAVE_AND_ADD_ANOTHER)
                ->addCssClass('btn btn-secondary action-save')
                ->displayAsButton()
                ->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => $actionName])
                ->linkToCrudAction(Action::NEW);
        }

        throw new \InvalidArgumentException(sprintf('The "%s" action is not a built-in action, so you can\'t add or configure it via its name. Either refer to one of the built-in actions or create a custom action called "%s".', $actionName, $actionName));
    }
}
