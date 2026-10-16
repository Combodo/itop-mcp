<?php
namespace App\Tests\Phpunit;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Service\iTopClient;
use Symfony\Bundle\SecurityBundle\Security;
use Psr\Log\LoggerInterface;

class iTopClientTest extends KernelTestCase
{
    protected LoggerInterface $logger;
    protected Security $security;
    
    public function setUp():void
    {
        // (1) boot the Symfony kernel
        self::bootKernel();
        
        // (2) use static::getContainer() to access the service container
        $container = static::getContainer();
        $this->logger = $container->get(LoggerInterface::class);
        $this->security = $container->get(Security::class);
    }
    
    public function testGetRestEndpointShortNoSlash()
    {
        $client = new iTopClient($this->security, $this->logger, 'http://www.itop.com/demo', 120, 120, false);
        $this->assertEquals('http://www.itop.com/demo/webservices/rest.php', $client->getRestEndpointUrl());
    }
    
    public function testGetRestEndpointShortSlash()
    {
        $client = new iTopClient($this->security, $this->logger, 'http://www.itop.com/demo/', 120, 120, false);
        $this->assertEquals('http://www.itop.com/demo/webservices/rest.php', $client->getRestEndpointUrl());
    }
    
    public function testGetRestEndpointLong()
    {
        $client = new iTopClient($this->security, $this->logger, 'http://www.itop.com/demo/webservices/rest.php', 120, 120, false);
        $this->assertEquals('http://www.itop.com/demo/webservices/rest.php', $client->getRestEndpointUrl());
    }
}
    