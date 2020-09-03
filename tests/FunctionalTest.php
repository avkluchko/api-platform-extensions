<?php

namespace AVKluchko\ApiPlatformExtensions\Tests;

use AVKluchko\ApiPlatformExtensions\ApiPlatformExtensionsBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class FunctionalTest extends TestCase
{
    public function testServiceWiring(): void
    {
        $kernel = new ApiPlatformExtensionsKernel();
        $kernel->boot();
        $container = $kernel->getContainer();

        self::assertInstanceOf(ContainerInterface::class, $container);
    }
}

class ApiPlatformExtensionsKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): array
    {
        return [
            new ApiPlatformExtensionsBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }

    public function getCacheDir(): string
    {
        return __DIR__ . '/cache/' . spl_object_hash($this);
    }
}
