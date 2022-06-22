<?php

namespace Base\Controller\Backend\Crud\Layout\Widget;

use Base\Field\FileField;
use Base\Field\SlugField;
use Base\Field\TranslationField;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

use Base\Controller\Backend\Crud\Layout\WidgetCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class AttachmentCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function downloadAttachment(AdminContext $context)
    {
        $attachment = $context->getEntity()->getInstance();

        $fileContent = $attachment->getFile()->getContent();
        $response = new Response($fileContent);

        $preferredDownloadName = $attachment->getSlug();
        if(($extension = $attachment->getFile()->guessExtension()))
            $preferredDownloadName .= ".".$extension;

        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,$preferredDownloadName);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function configureActions(Actions $actions): Actions
    {
        $downloadAction = Action::new('download', "Download", 'fas fa-fw fa-download')
            ->linkToCrudAction("downloadAttachment");

        return parent::configureActions($actions)->add(Crud::PAGE_INDEX, $downloadAction);
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, [
            "id" => function () {

                yield FileField::new('file')->setColumns(6)->setPreferredDownloadName("slug");
                yield SlugField::new('slug')->setColumns(6)->hideOnIndex()->setTargetFieldName("translations.title");

            }], $args);
    }
}