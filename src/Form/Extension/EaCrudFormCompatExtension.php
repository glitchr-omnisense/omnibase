<?php

namespace Base\Form\Extension;

use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudFormType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\Layout\EaFormFieldsetCloseType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\Layout\EaFormTabPaneCloseType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Backward-compatibility shim for the ea_crud_form variable removed in EasyAdmin 5.
 *
 * EA 5 dropped the ea_crud_form variable from both the root CRUD form view (which
 * provided form_tabs / form_fieldsets) and from individual field views (which provided
 * form_panel, form_tab, ea_field, ea_entity).  This extension reconstructs that
 * data structure by walking the form children so that templates written against the
 * EA 4 variable shape continue to work unchanged.
 */
class EaCrudFormCompatExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [CrudFormType::class];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $formTabs = [];
        $formFieldsets = [];
        $currentTab = null;
        $fieldsetIndex = 0;
        $currentFieldset = 0;
        $hasUngroupedFields = false;

        foreach ($form as $childForm) {
            /** @var \EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto|null $fieldDto */
            $fieldDto = $childForm->getConfig()->getAttribute('ea_field');
            if (null === $fieldDto) {
                continue;
            }

            $childView = $view->children[$childForm->getName()] ?? null;
            if (null === $childView) {
                continue;
            }

            if ($fieldDto->isFormTab()) {
                // EaFormTabPaneOpenType: start a new tab pane
                $tabLabel = (string) $fieldDto->getLabel();
                $formTabs[$tabLabel] = new \ArrayObject([
                    'active'    => (bool) $fieldDto->getCustomOption(FormField::OPTION_TAB_IS_ACTIVE),
                    'errors'    => (int) ($fieldDto->getCustomOption(FormField::OPTION_TAB_ERROR_COUNT) ?? 0),
                    'id'        => $fieldDto->getCustomOption(FormField::OPTION_TAB_ID),
                    'label'     => $fieldDto->getLabel(),
                    'help'      => $fieldDto->getHelp(),
                    'icon'      => $fieldDto->getCustomOption(FormField::OPTION_ICON),
                    'css_class' => $fieldDto->getCssClass(),
                ]);
                $currentTab = $tabLabel;
            } elseif (EaFormTabPaneCloseType::class === $fieldDto->getFormType()) {
                // EaFormTabPaneCloseType: leaving the current tab pane
                $currentTab = null;
            } elseif ($fieldDto->isFormFieldset()) {
                // EaFormFieldsetOpenType: start a new fieldset/panel
                $currentFieldset = ++$fieldsetIndex;
                $formFieldsets[$currentFieldset] = [
                    'form_tab'    => $currentTab,
                    'label'       => $fieldDto->getLabel(),
                    'icon'        => $fieldDto->getCustomOption(FormField::OPTION_ICON),
                    'collapsible' => $fieldDto->getCustomOption(FormField::OPTION_COLLAPSIBLE),
                    'collapsed'   => $fieldDto->getCustomOption(FormField::OPTION_COLLAPSED),
                    'help'        => $fieldDto->getHelp(),
                    'css_class'   => $fieldDto->getCssClass(),
                ];
            } elseif (EaFormFieldsetCloseType::class === $fieldDto->getFormType()) {
                // EaFormFieldsetCloseType: back to the "ungrouped" state (panel 0)
                $currentFieldset = 0;
            } elseif (!$fieldDto->isFormLayoutField()) {
                // Regular editable field: tag it with its current tab and panel context
                $panelKey = $currentFieldset; // 0 means "ungrouped / no explicit fieldset"
                $childView->vars['ea_crud_form'] ??= [
                    'form_panel'    => $panelKey ?: null,
                    'form_fieldset' => $panelKey ?: null,
                    'form_tab'      => $currentTab,
                    'ea_field'      => $fieldDto,
                    'ea_entity'     => $childForm->getConfig()->getAttribute('ea_entity'),
                ];
                if (0 === $panelKey) {
                    $hasUngroupedFields = true;
                }
            }
        }

        // When a form has no explicit fieldsets but does have regular fields, add an
        // implicit panel (key 0) so the ea_crud_widget_panels template block renders them.
        if ($hasUngroupedFields && !isset($formFieldsets[0])) {
            $formFieldsets[0] = [
                'form_tab'    => null,
                'label'       => false,
                'icon'        => null,
                'collapsible' => false,
                'collapsed'   => false,
                'help'        => null,
                'css_class'   => null,
            ];

            // Also patch every ungrouped field so form_panel = 0 (not null) to
            // match the implicit panel key added above.
            foreach ($view->children as $childView) {
                if (isset($childView->vars['ea_crud_form'])
                    && null === $childView->vars['ea_crud_form']['form_panel']
                    && null === $childView->vars['ea_crud_form']['form_tab']
                ) {
                    $childView->vars['ea_crud_form']['form_panel'] = 0;
                    $childView->vars['ea_crud_form']['form_fieldset'] = 0;
                }
            }
        }

        // Merge into root form ea_crud_form (CrudFormType may have already set some keys
        // like 'assets' and 'entity' in earlier EA versions)
        $view->vars['ea_crud_form'] = array_merge(
            $view->vars['ea_crud_form'] ?? [],
            [
                'entity'         => $options['entityDto'],
                'form_tabs'      => $formTabs,
                'form_fieldsets' => $formFieldsets,
            ]
        );
    }
}
