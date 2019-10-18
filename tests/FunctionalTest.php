<?php

namespace AVKluchko\ApiPlatformExtensions\Tests;

use AVKluchko\ApiPlatformExtensions\ApiPlatformExtensionsBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class FunctionalTest extends TestCase
{
    public function testServiceWiring()
    {
        $kernel = new ApiPlatformExtensionsKernel();
        $kernel->boot();
        $container = $kernel->getContainer();

//        $reader = $container->get('avkluchko_x509.certificate_reader');
//        $this->assertInstanceOf(CertificateReader::class, $reader);
//
//        $parser = $container->get('avkluchko_x509.parser');
//        $this->assertInstanceOf(Parser::class, $parser);
    }
}

class ApiPlatformExtensionsKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles()
    {
        return [
            new ApiPlatformExtensionsBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }

    public function getCacheDir()
    {
        return __DIR__ . '/cache/' . spl_object_hash($this);
    }
}