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

class SettingRepository extends ServiceEntityRepository implements Settings
{
    /** Settings (step R28): the finders SettingBag::getRaw() named by its arguments. */
    public function under(?string $path, bool $linkedBagOnly = false, bool $cached = true): array
    {
        $fn = $cached ? "cache" : "find";
        $fn .= $linkedBagOnly ?
            ($path ? "ByInsensitivePathStartingWithAndBagNotEmpty" : "ByBagNotEmpty") :
            ($path ? "ByInsensitivePathStartingWith" : "All");

        $settings = $this->$fn(...($path ? [$path] : []));

        return is_array($settings) ? $settings : $settings->getResult();
    }

    public function oneByInsensitivePath(string $path): ?Setting
    {
        return $this->findOneByInsensitivePath($path);
    }
}
