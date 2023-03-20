<?php

namespace Base\Repository\Layout\Widget;

use Base\Entity\Layout\Widget\Attachment;

use Base\Repository\Layout\WidgetRepository;

/**
 * @method Attachment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Attachment|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Attachment[]    findAll()
 * @method Attachment[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class AttachmentRepository extends WidgetRepository
{
}
