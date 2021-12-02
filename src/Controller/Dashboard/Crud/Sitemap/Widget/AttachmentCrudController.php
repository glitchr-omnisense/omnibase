<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\FileField;
use Base\Field\SlugField;
use Base\Field\TranslatableField;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

use Base\Controller\Dashboard\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class AttachmentCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-paperclip"; } 

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

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield FileField::new('file')->setPreferredDownloadName("slug");
        foreach ( ($callbacks["file"] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield SlugField::new('slug')->hideOnIndex()->setTargetFieldName("translations.title");

        yield TranslatableField::new()->showOnIndex('title');
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;

    }
}