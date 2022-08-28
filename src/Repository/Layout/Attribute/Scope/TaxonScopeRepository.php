<?php

namespace Base\Repository\Layout\Attribute\Scope;

use Base\Entity\Layout\Attribute\Scope\TaxonScope;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method TaxonScope|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaxonScope|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method TaxonScope[]    findAll()
 * @method TaxonScope[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class TaxonScopeRepository extends AttributeRepository
{

}
