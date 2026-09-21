<?php
namespace App\Tests\Phpunit;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Service\iTopClient;
use App\Tools\core\get\iTopGetTools;
use Twig\Environment;
use Psr\Log\LoggerInterface;
use App\Service\DatamodelService;

class ToolsFromTemplateTest extends KernelTestCase
{
    protected Environment $twigEnvironment;
    protected LoggerInterface $logger;
    protected DatamodelService $datamodel;

    public function setUp():void
    {
        // (1) boot the Symfony kernel
        self::bootKernel();
        
        // (2) use static::getContainer() to access the service container
        $container = static::getContainer();
        $this->twigEnvironment = $container->get(Environment::class);
        $this->logger = $container->get(LoggerInterface::class);
        $this->datamodel = $container->get(DatamodelService::class);
    }

    public function testGetPersonFromEmail(): void
    {
        // Mock the iTopClient service, to check that the templating works
        $input =
<<<JSON
{
  "operation": "core/get",
  "class": "Person",
  "key": "SELECT Person WHERE email = 'test\u0040demo.com'",
  "output_fields": "friendlyname,name,org_id,status,location_id,email,phone"
}
JSON;
        $output = [
            'objects' => [
                'Person::1' => [
                    'class' => 'Person',
                    'key' => 1,
                    'fields' => [
                        'friendlyname' => 'Test Person',
                        'email' => 'test@demo.com',
                        'org_id' => 1,
                    ]
                ]
            ]
        ];
        $mockiTopClient = $this->MockiTopClientThatWillReturn($input, $output);
        
        $expected  =
<<<EXPECTED
class Person:
  id, friendlyname, email, org_id

Person  {
  1, "Test Person", "test@demo.com", "1"
}

EXPECTED;

        $tools = new iTopGetTools($this->twigEnvironment, $mockiTopClient, $this->logger, $this->datamodel);
        $this->assertEquals($expected, $tools->getPersonFromEmail('test@demo.com'));
    }
    
    public function testGetPersonFromTelephone(): void
    {
        // Mock the iTopClient service, to check that the templating works
        $input =
<<<JSON
{
  "operation": "core/get",
  "class": "Person",
  "key": "SELECT\u0020Person\u0020WHERE\u0020phone\u0020\u003D\u0020\u0027123456789\u0027\u0020OR\u0020mobile_phone\u0020\u003D\u0020\u0027123456789\u0027",
  "output_fields": "friendlyname,name,org_id,status,location_id,email,phone"
}
JSON;
        $output = [
            'objects' => [
              'Person::1' => [
                'class' => 'Person',
                'key' => 1,
                'fields' => [
                    'friendlyname' => 'Test Person',
                    'email' => 'test@demo.com',
                    'org_id' => 1,
                 ]
               ]
            ]
        ];
        $mockiTopClient = $this->MockiTopClientThatWillReturn($input, $output);
        
        $tools = new iTopGetTools($this->twigEnvironment, $mockiTopClient, $this->logger, $this->datamodel);
        $expected =
<<<EXPECTED
class Person:
  id, friendlyname, email, org_id

Person  {
  1, "Test Person", "test@demo.com", "1"
}
EXPECTED;
        
        $this->assertEquals(trim($expected), trim($tools->getPersonFromTelephone('123456789')));
    }
    
    public function testSearchAnyObjectByOql()
    {
        // Mock the iTopClient service, to check that the templating works
        $oql = "SELECT Person WHERE name LIKE '%Smith%'";
        $input =
<<<JSON
{
  "operation":"core/get",
  "class": "Person",
  "key": "SELECT\u0020Person\u0020WHERE\u0020name\u0020LIKE\u0020\u0027\u0025Smith\u0025\u0027",
  "limit": 20,
  "page": 1,
  "output_fields": "friendlyname,name,org_id,status,location_id,email,phone"
}
JSON;
        $output = [
            'objects' => [
                'Person::1' => [
                    'class' => 'Person',
                    'key' => 1,
                    'fields' => ['friendlyname' => 'John Smith', 'email' => 'john.smith@demo.com'],
                ],
            ],
        ];
        $mockiTopClient = $this->MockiTopClientThatWillReturn($input, $output);
        
        $expected =
<<<JSON
class Person:
  id, friendlyname, email

Person  {
  1, "John Smith", "john.smith@demo.com"
}
JSON;   
        $tools = new iTopGetTools($this->twigEnvironment, $mockiTopClient, $this->logger, $this->datamodel);
        $this->assertEquals(trim($expected), trim($tools->searchAnyObjectByOql($oql)));
    }

    protected function MockiTopClientThatWillReturn(string $inputData, array $outputData)
    {
        $mockiTopClient = $this->createMock(iTopClient::class);
        $mockiTopClient->expects(self::once())
        ->method('postJsonToItop')
        ->with(trim($inputData))
        ->willReturn(json_encode($outputData));
        return $mockiTopClient;
    }
    
    public function testQuoteString(): void
    {
        $this->assertEquals("O\\'Reilly", iTopGetTools::quoteString("O'Reilly"));
        $this->assertEquals("Line1\\nLine2", iTopGetTools::quoteString("Line1\nLine2"));
        $this->assertEquals("Tab\\tSeparated", iTopGetTools::quoteString("Tab\tSeparated"));
        $this->assertEquals("Percent\\%Sign", iTopGetTools::quoteString("Percent%Sign"));
        $this->assertEquals("Underscore\\_Test", iTopGetTools::quoteString("Underscore_Test"));
        $this->assertEquals("Backslash\\\\Test", iTopGetTools::quoteString("Backslash\\Test"));
    }
}