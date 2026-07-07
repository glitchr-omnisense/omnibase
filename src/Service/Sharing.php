<?php

namespace Base\Service;

use Base\Service\Model\LinkableInterface;
use Base\Service\Model\Sharing\SharingAdapterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Sharing
{
    protected $adapters = [];

    /**
     * @return array|mixed
     */
    public function getAdapters()
    {
        return $this->adapters;
    }

    public function getAdapter(string $idOrClass): ?SharingAdapterInterface
    {
        if (class_exists($idOrClass)) {
            return $this->adapters[$idOrClass] ?? null;
        }

        foreach ($this->adapters as $adapter) {
            if ($adapter->getIdentifier() == $idOrClass) {
                return $adapter;
            }
        }

        return null;
    }

    public function addAdapter(SharingAdapterInterface $adapter): self
    {
        $this->adapters[get_class($adapter)] = $adapter;
        return $this;
    }

    public function removeAdapter(SharingAdapterInterface $adapter): self
    {
        // array_values_remove() is pure — without the assignment this was a no-op.
        $this->adapters = array_values_remove($this->adapters, $adapter);
        return $this;
    }

    /**
     * @param string $adapterId
     * @param LinkableInterface|string $url
     * @param array $options
     * @param string|null $template
     * @return string
     */
    public function generate(string $adapterId, LinkableInterface|string $url, array $options = [], ?string $template = null)
    {
        $adapter = $this->getAdapter($adapterId);
        if (!$adapter) {
            return "";
        }

        $options["url"] = $url instanceof LinkableInterface ? $url->__toLink([], UrlGeneratorInterface::ABSOLUTE_URL) : $url;
        return $adapter->generate($options, $template);
    }
}
