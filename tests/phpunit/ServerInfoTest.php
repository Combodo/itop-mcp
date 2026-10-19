<?php
namespace App\Tests\Phpunit;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ServerInfoTest extends WebTestCase
{
    /**
     * The serverInfo sent back on initialize is what a client names the server
     * after (VS Code prefixes every tool with it), so it must come from the
     * configuration (mcp.app, mcp.version), not from the SDK's 'app'/'dev'
     * defaults.
     */
    public function testInitializeReportsTheConfiguredServerInfo(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $client->request('POST', '/_mcp', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
        ], json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ]));

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame($container->getParameter('mcp.app'), $response['result']['serverInfo']['name']);
        $this->assertSame($container->getParameter('mcp.version'), $response['result']['serverInfo']['version']);
        $this->assertNotSame('app', $response['result']['serverInfo']['name']);
    }
}
