<?php

namespace Base\Field\Type;

use Symfony\Component\Form\AbstractType;

class ImageType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'imageupload';
    }

    public function getParent()
    {
        return FileType::class;
    }
}