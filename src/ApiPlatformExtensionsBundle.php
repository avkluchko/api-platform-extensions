<?php

namespace AVKluchko\ApiPlatformExtensions;

use AVKluchko\ApiPlatformExtensions\DependencyInjection\ApiPlatformExtensionsExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ApiPlatformExtensionsBundle extends Bundle
{
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new ApiPlatformExtensionsExtension();
        }

        return $this->extension;
    }
}