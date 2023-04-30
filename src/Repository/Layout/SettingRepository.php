<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Setting;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Setting|null find($id, $lockMode = null, $lockVersion = null)
 * @method Setting|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Setting[]    findAll()
 * @method Setting[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class SettingRepository extends ServiceEntityRepository
{
}
