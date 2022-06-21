<?php

namespace Base\Backend\Config\Menu;

use Base\Backend\Config\WidgetItem;
use Base\Controller\Backoffice\AbstractCrudController;
use Exception;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;

use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\SortOrder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\MenuItemTrait;

final class CrudWidgetItem implements MenuItemInterface
{
    use MenuItemTrait;

    public function __construct(string $label, ?string $icon, string $crudController)
    {
        $this->dto = new MenuItemDto();

        $this->dto->setType(MenuItemDto::TYPE_CRUD);
        $this->dto->setLabel($label);
        $this->dto->setIcon($icon);
        $this->dto->setRouteParameters([
            EA::CRUD_ACTION => 'index',
            EA::CRUD_CONTROLLER_FQCN => $crudController,
            EA::ENTITY_ID => null,
        ]);
    }

    public function setController(string $controllerFqcn): self
    {
        $this->dto->setRouteParameters(array_merge(
            $this->dto->getRouteParameters(),
            [EA::CRUD_CONTROLLER_FQCN => $controllerFqcn]
        ));

        return $this;
    }

    public function setAction(string $actionName): self
    {
        $this->dto->setRouteParameters(array_merge(
            $this->dto->getRouteParameters(),
            [EA::CRUD_ACTION => $actionName]
        ));

        return $this;
    }

    public function setEntityId($entityId): self
    {
        $this->dto->setRouteParameters(array_merge(
            $this->dto->getRouteParameters(),
            [EA::ENTITY_ID => $entityId]
        ));

        return $this;
    }

    /**
     * @param array $sortFieldsAndOrder ['fieldName' => 'ASC|DESC', ...]
     */
    public function setDefaultSort(array $sortFieldsAndOrder): self
    {
        $sortFieldsAndOrder = array_map('mb_strtoupper', $sortFieldsAndOrder);
        foreach ($sortFieldsAndOrder as $sortField => $sortOrder) {
            if (!\in_array($sortOrder, [SortOrder::ASC, SortOrder::DESC])) {
                throw new \InvalidArgumentException(sprintf('The sort order can be only "ASC" or "DESC", "%s" given.', $sortOrder));
            }

            if (!\is_string($sortField)) {
                throw new \InvalidArgumentException(sprintf('The keys of the array that defines the default sort must be strings with the field names, but the given "%s" value is a "%s".', $sortField, \gettype($sortField)));
            }
        }

        $this->dto->setRouteParameters(array_merge(
            $this->dto->getRouteParameters(),
            [EA::SORT => $sortFieldsAndOrder]
        ));

        return $this;
    }

    public function generateUrl()
    {
        if (WidgetItem::$adminUrlGenerator == null)
            throw new Exception("AdminUrlGenerator is missing");
        if (WidgetItem::$adminContextProvider == null)
            throw new Exception("AdminContextProvider is missing");

        $itemDto = $this->getAsDto();
        WidgetItem::$adminUrlGenerator->unsetAll();
        if ($itemDto->getType() === MenuItemDto::TYPE_CRUD) {

            $routeParameters = $itemDto->getRouteParameters();
            $entityFqcn = $routeParameters[EA::ENTITY_FQCN] ?? null;
            $crudControllerFqcn = $routeParameters[EA::CRUD_CONTROLLER_FQCN] ?? null;
            if (null === $entityFqcn && null === $crudControllerFqcn) {
                throw new \RuntimeException(sprintf('The CRUD menuitem with label "%s" must define either the entity FQCN (using the third constructor argument) or the CRUD Controller FQCN (using the "setController()" method).', $itemDto->getLabel()));
            }

            // 1. if CRUD controller is defined, use it...
            if (null !== $crudControllerFqcn) {
                WidgetItem::$adminUrlGenerator->setController($crudControllerFqcn);
                // 2. ...otherwise, find the CRUD controller from the entityFqcn
            } else {

                $crudControllers = WidgetItem::$adminContextProvider->getContext()->getCrudControllers();
                if (null === $controllerFqcn = AbstractCrudController::getCrudControllerFqcn($entityFqcn)) {
                    throw new \RuntimeException(sprintf('Unable to find the controller related to the "%s" Entity; did you forget to extend "%s"?', $entityFqcn, AbstractCrudController::class));
                }

                WidgetItem::$adminUrlGenerator->setController($controllerFqcn);
                WidgetItem::$adminUrlGenerator->unset(EA::ENTITY_FQCN);
            }

            $url = WidgetItem::$adminUrlGenerator->generateUrl();
            $itemDto->setLinkUrl($url);

            return $url;
        }

        return null;
    }
}
