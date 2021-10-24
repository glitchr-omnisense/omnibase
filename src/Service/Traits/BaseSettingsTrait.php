<?php

namespace Base\Service\Traits;

use Base\Entity\Sitemap\Setting;
use Base\Service\BaseService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

trait BaseSettingsTrait
{
    private $settings;
    protected $settingRepository = null;

    public function list()
    {
        return $this->settings;
    }

    public function all()
    {
        foreach($this->settingRepository->findAll() as $setting)
        {
            $name  = $setting->getName();
            $value = $setting->getValue();

            $this->settings[$name] = $value;
        }

        return $this->settings;
    }

    public function get(string $name, ?string $locale = null)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->settings[$name] = $this->settings[$name] ?? $this->settingRepository->findOneByName($name);
        if(!$this->settings[$name]) 
            return null;

        return $this->settings[$name]->translate($locale)->getValue();
    }

    public function has(string $name)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->settings[$name] = $this->settings[$name] ?? $this->settingRepository->findOneByName($name);
        if(!$this->settings[$name]) 
            return null;

        return $this->settings[$name] != null;
    }

    public function set(string $name, string $value, ?string $locale = null)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->settings[$name] = $this->settings[$name] ?? $this->settingRepository->findOneByName($name);
        if($this->settings[$name]) {
            
            $this->settings[$name]->translate($locale)->setValue($value);
            $this->settingRepository->flush();
        }

        return $this;
    }

    public function remove(string $name)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->settings[$name] = $this->settingRepository->findOneByName($name);
        if($this->settings[$name])
            unset($this->settings[$name]);

        return $this;
    }
}