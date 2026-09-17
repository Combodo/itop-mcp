<?php
namespace App\Tests\Phpunit;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Service\DatamodelService;
use Symfony\Contracts\Cache\CacheInterface;


class DatamodelServiceTest extends KernelTestCase
{
    protected CacheInterface $cache;

    public function setUp():void
    {
        // (1) boot the Symfony kernel
        self::bootKernel();
        
        // (2) use static::getContainer() to access the service container
        $container = static::getContainer();
        $this->cache = $container->get(CacheInterface::class);
    }

    public function testGetClassesFromXml(): void
    {
        $service = new DatamodelService(__DIR__.'/../../data/datamodel-production.xml', $this->cache, 'FR FR');
        $list = $service->getListZlist('Person');
        $this->assertEquals('friendlyname,name,org_id,status,location_id,email,phone', $list);
    }
    
    public function testGetClassSchema(): void
    {
        $service = new DatamodelService(__DIR__.'/../../data/datamodel-production.xml', $this->cache, 'FR FR');
        $schema = $service->getClassSchema('Person');
        $expected = 'name,status,org_id,org_name,email,phone,notify,function,cis_list,picture,first_name,employee_number,mobile_phone,location_id,location_name,manager_id,manager_name,team_list,user_list,tickets_list';
        $this->assertEquals(explode(',', $expected), array_keys($schema['fields']));
    }
    
    public function testGetClassFromOQLOk(): void
    {
        $service = new DatamodelService(__DIR__.'/../../data/datamodel-production.xml', $this->cache, 'FR FR');
        $this->assertEquals('Person', $service->getClassFromOQL("SELECT Person WHERE name='Foo'"));
    }
    
    public function testGetClassFromOQLKo(): void
    {
        $service = new DatamodelService(__DIR__.'/../../data/datamodel-production.xml', $this->cache, 'FR FR');
        
        $this->expectException("InvalidArgumentException");
        $this->assertEquals('Person', $service->getClassFromOQL("SELECT 123456 WHERE name='Foo'"));
        
        $this->expectException("InvalidArgumentException");
        $this->assertEquals('Person', $service->getClassFromOQL("NOT A SELECT Person"));
        
    }
}


