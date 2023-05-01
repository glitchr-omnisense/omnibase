<?php

namespace Base\Backend\Config;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Service\Model\LinkableInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action as EaAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use Exception;
use function Symfony\Component\Translation\t;

class Actions extends \EasyCorp\Bundle\EasyAdminBundle\Config\Actions
{
    protected AdminUrlGenerator $adminUrlGenerator;
    protected EntityManagerInterface $entityManager;

    protected function __construct(ActionConfigDto $actionConfigDto, AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager)
    {
        $this->dto = $actionConfigDto;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
    }

    public static function new(...$args)
    {
        $dto = new ActionConfigDto();
        return new static($dto, ...$args);
    }

    /**
     * The $pageName is needed because sometimes the same action has different config
     * depending on where it's displayed (to display an icon in 'detail' but not in 'index', etc.).
     */
    protected function createBuiltInAction(string $pageName, string $actionName): EaAction
    {
        if (Action::SEPARATOR === $actionName) {
            return Action::new(Action::SEPARATOR, "")
                ->setCssClass('action-' . Action::SEPARATOR)
                ->displayAsSeparator()
                ->linkToCrudAction(Action::SEPARATOR);
        }

        if (Action::GROUP === $actionName) {
            return Action::new(Action::GROUP, ":::")
                ->setCssClass('action-' . Action::GROUP)
                ->displayAsDropdown()
                ->linkToCrudAction(Action::GROUP);
        }

        if (Action::SAVE_AND_RETURN === $actionName) {
            return Action::new(Action::SAVE_AND_RETURN, t(Crud::PAGE_EDIT === $pageName ? 'action.save' : 'action.create', domain: 'EasyAdminBundle'))
                ->setCssClass('action-' . Action::SAVE_AND_RETURN)
                ->addCssClass('btn btn-primary action-save')
                ->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => $actionName])
                ->displayAsButton()
                ->renderAsTooltip()
                ->linkToCrudAction(Crud::PAGE_EDIT === $pageName ? Action::EDIT : Action::NEW);
        }

        if (Action::SAVE_AND_CONTINUE === $actionName) {
            return Action::new(Action::SAVE_AND_CONTINUE, t(Crud::PAGE_EDIT === $pageName ? 'action.save_and_continue' : 'action.create_and_continue', domain: 'EasyAdminBundle'), 'fa-regular fa-edit')
                ->setCssClass('action-' . Action::SAVE_AND_CONTINUE)
                ->addCssClass('btn btn-secondary action-save text-success')
                ->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => $actionName])
                ->displayAsButton()
                ->renderAsTooltip()
                ->linkToCrudAction(Crud::PAGE_EDIT === $pageName ? Action::EDIT : Action::NEW);
        }

        if (Action::GOTO_PREV === $actionName) {
            return Action::new(Action::GOTO_PREV, t('action.goto_prev', domain: 'backoffice'))
                ->setCssClass('action-' . Action::GOTO_PREV)
                ->addCssClass('btn btn-secondary')
                ->displayAsLink()
                ->renderAsTooltip()
                ->linkToUrl(function (mixed $entity) {
                    $prevEntity = null;

                    $entityRepository = $this->entityManager->getRepository(get_class($entity));
                    if ($entityRepository instanceof ServiceEntityRepository) {
                        if (get_parent_class($entity) !== false) {
                            $prevEntity = $entityRepository->cachePreviousOneByClassOf($entity->getId(), get_class($entity));
                        } else {
                            $prevEntity = $entityRepository->cachePreviousOne($entity->getId());
                        }
                    }

                    return $prevEntity ? $this->adminUrlGenerator->setEntityId($prevEntity->getId())->generateUrl() : "";
                });
        }

        if (Action::GOTO_SEE === $actionName) {
            return Action::new(Action::GOTO_SEE, t('action.goto_see', domain: 'backoffice'))
                ->setCssClass('action-' . Action::GOTO_SEE)
                ->addCssClass('btn btn-secondary')
                ->displayAsLink()
                ->renderAsTooltip()
                ->linkToUrl(function (mixed $entity) {
                    try {
                        $linkToEntity = $entity instanceof LinkableInterface ? $entity->__toLink() : "";
                    } catch (Exception $e) {
                        $linkToEntity = "";
                    }

                    return $linkToEntity;
                });
        }

        if (Action::GOTO_NEXT === $actionName) {
            return Action::new(Action::GOTO_NEXT, t('action.goto_next', domain: 'backoffice'))
                ->setCssClass('action-' . Action::GOTO_NEXT)
                ->addCssClass('btn btn-secondary')
                ->displayAsLink()
                ->renderAsTooltip()
                ->linkToUrl(function (mixed $entity) {
                    $nextEntity = null;

                    $entityRepository = $this->entityManager->getRepository(get_class($entity));
                    if ($entityRepository instanceof ServiceEntityRepository) {
                        if (get_parent_class($entity) !== false) {
                            $nextEntity = $entityRepository->cacheNextOneByClassOf($entity->getId(), get_class($entity));
                        } else {
                            $nextEntity = $entityRepository->cacheNextOneBy($entity->getId());
                        }
                    }

                    return $nextEntity ? $this->adminUrlGenerator->setEntityId($nextEntity->getId())->generateUrl() : "";
                });
        }

        return parent::createBuiltInAction($pageName, $actionName);
    }

    public function add(string $pageName, EaAction|string $actionNameOrObject, ?string $actionIcon = null, callable $callable = null)
    {
        parent::add($pageName, $actionNameOrObject);
        $actionDto = $this->dto->getAction($pageName, $actionNameOrObject);
        if ($actionIcon) {
            $actionDto->setIcon($actionIcon);
        }

        if ($callable != null) {
            parent::update($pageName, $actionNameOrObject, $callable);
        }

        return $this;
    }
}
