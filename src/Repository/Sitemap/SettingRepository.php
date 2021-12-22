<?php

namespace Base\Repository\Sitemap;

use Base\Entity\Sitemap\Setting;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Setting|null find($id, $lockMode = null, $lockVersion = null)
 * @method Setting|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Setting[]    findAll()
 * @method Setting[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class SettingRepository extends ServiceEntityRepository
{

}
