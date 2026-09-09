<?php

/*
 * This file is part of the official PHP MCP SDK.
 *
 * A collaboration between Symfony and the PHP Foundation.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Capability;

use Mcp\Server\Builder;
use Mcp\Capability\Discovery\CachedDiscoverer;
use Mcp\Capability\Discovery\Discoverer;
use Mcp\Capability\Discovery\DiscovererInterface;
use Mcp\Capability\Discovery\SchemaGeneratorInterface;
use Mcp\Capability\Registry;
use Mcp\Capability\Registry\Container;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\Loader\ArrayLoader;
use Mcp\Capability\Registry\Loader\DiscoveryLoader;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Capability\RegistryInterface;
use Mcp\JsonRpc\MessageFactory;
use Mcp\Schema\Annotations;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Icon;
use Mcp\Schema\Implementation;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Handler\Notification\NotificationHandlerInterface;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Resource\SessionSubscriptionManager;
use Mcp\Server\Resource\SubscriptionManagerInterface;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\SessionFactory;
use Mcp\Server\Session\SessionFactoryInterface;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Mcp\Server\Configuration;
use App\Service\iTopClientInterface;
use Twig\Cache\FilesystemCache;
//use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface as sfCacheInterface;

/**
 * @phpstan-import-type Handler from ElementReference
 */
class iTopBuilder
{
    private Builder $builder;
    private ?string $instructions = null;

    public function __construct(private iTopClientInterface $iTopClient, private sfCacheInterface $restOperationsCache, private string $projectDir, private LoggerInterface $mcpLogger, private string $instructionsAddendumFile = '')
    {
        $this->builder = new Builder();
    }

    public function setServerInfo(
        string $name,
        string $version,
        ?string $description = null,
        ?array $icons = null,
        ?string $websiteUrl = null,
    ): self
    {
        $this->builder->setServerInfo($name, $version, $description, $icons, $websiteUrl);

        return $this;
    }

    /**
     * Configures the server's pagination limit.
     */
    public function setPaginationLimit(int $paginationLimit): self
    {
        $this->builder->setPaginationLimit($paginationLimit);

        return $this;
    }

    /**
     * Configures the instructions describing how to use the server and its features.
     *
     * This can be used by clients to improve the LLM's understanding of available tools, resources,
     * etc. It can be thought of like a "hint" to the model. For example, this information MAY
     * be added to the system prompt.
     *
     * If APP_INSTRUCTIONS_ADDENDUM_FILE points at a readable file, its content is appended to
     * these instructions when the server is built - see build() and getFinalInstructions(). This
     * lets a deployment add its own operating rules (data quality, local workflows, ...) without
     * editing this value or the application's tracked config.
     */
    public function setInstructions(?string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * Appends the content of APP_INSTRUCTIONS_ADDENDUM_FILE, if configured and readable, to the
     * instructions set via setInstructions().
     */
    protected function getFinalInstructions(): ?string
    {
        $addendumFile = trim($this->instructionsAddendumFile);
        if ($addendumFile === '') {
            return $this->instructions;
        }
        $path = $this->projectDir.'/'.$addendumFile;
        if (!is_file($path) || !is_readable($path)) {
            return $this->instructions;
        }
        $addendum = trim(file_get_contents($path));
        if ($addendum === '') {
            return $this->instructions;
        }
        return trim((string)$this->instructions)."\n\n".$addendum;
    }

    /**
     * Explicitly set server capabilities. If set, this overrides automatic detection.
     */
    public function setCapabilities(ServerCapabilities $serverCapabilities): self
    {
        $this->builder->setCapabilities($serverCapabilities);

        return $this;
    }

    /**
     * Register a single custom method handler.
     *
     * @param RequestHandlerInterface<mixed> $handler
     */
    public function addRequestHandler(RequestHandlerInterface $handler): self
    {
        $this->builder->addRequestHandler($handler);

        return $this;
    }

    /**
     * Register multiple custom method handlers.
     *
     * @param iterable<RequestHandlerInterface<mixed>> $handlers
     */
    public function addRequestHandlers(iterable $handlers): self
    {
        $this->builder->addRequestHandlers($handlers);

        return $this;
    }

    /**
     * Register a single custom notification handler.
     */
    public function addNotificationHandler(NotificationHandlerInterface $handler): self
    {
        $this->builder->addNotificationHandler($handler);

        return $this;
    }

    /**
     * Register multiple custom notification handlers.
     *
     * @param iterable<int, NotificationHandlerInterface> $handlers
     */
    public function addNotificationHandlers(iterable $handlers): self
    {
        $this->builder->addNotificationHandlers($handlers);

        return $this;
    }

    public function setRegistry(RegistryInterface $registry): self
    {
        $this->builder->setRegistry($registry);

        return $this;
    }

    /**
     * Provides a PSR-3 logger instance. Defaults to NullLogger.
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->builder->setLogger($logger);

        return $this;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->builder->setEventDispatcher($eventDispatcher);

        return $this;
    }

    /**
     * Provides a PSR-11 DI container, primarily for resolving user-defined handler classes.
     * Defaults to a basic internal container.
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->builder->setContainer($container);

        return $this;
    }

    public function setSchemaGenerator(SchemaGeneratorInterface $schemaGenerator): self
    {
        $this->builder->setSchemaGenerator($schemaGenerator);

        return $this;
    }

    public function setDiscoverer(DiscovererInterface $discoverer): self
    {
        $this->builder->setDiscoverer($discoverer);

        return $this;
    }

    public function setResourceSubscriptionManager(SubscriptionManagerInterface $subscriptionManager): self
    {
        $this->builder->setResourceSubscriptionManager($subscriptionManager);

        return $this;
    }

    public function setSession(
        SessionStoreInterface $sessionStore,
        SessionFactoryInterface $sessionFactory = new SessionFactory(),
        int $ttl = 3600,
    ): self
    {
        $this->builder->setSession($sessionStore, $sessionFactory, $ttl);

        return $this;
    }

    /**
     * @param string[] $scanDirs
     * @param string[] $excludeDirs
     */
    public function setDiscovery(
        string $basePath,
        array $scanDirs = ['.', 'src'],
        array $excludeDirs = [],
        ?CacheInterface $cache = null,
    ): self
    {
        $this->builder->setDiscovery($basePath, $scanDirs, $excludeDirs, $cache);

        return $this;
    }

    public function setProtocolVersion(ProtocolVersion $protocolVersion): self
    {
        $this->builder->setProtocolVersion($protocolVersion);

        return $this;
    }

    /**
     * Manually registers a tool handler.
     *
     * @param Handler                   $handler
     * @param array<string, mixed>|null $inputSchema
     * @param ?Icon[]                   $icons
     * @param array<string, mixed>|null $meta
     * @param array<string, mixed>|null $outputSchema
     */
    public function addTool(
        callable|array|string $handler,
        ?string $name = null,
        ?string $description = null,
        ?ToolAnnotations $annotations = null,
        ?array $inputSchema = null,
        ?array $icons = null,
        ?array $meta = null,
        ?array $outputSchema = null,
    ): self
    {
        $this->builder->addTool($handler, $name, $description, $annotations, $inputSchema, $icons, $meta, $outputSchema);

        return $this;
    }

    /**
     * Manually registers a resource handler.
     *
     * @param Handler                   $handler
     * @param ?Icon[]                   $icons
     * @param array<string, mixed>|null $meta
     */
    public function addResource(
        \Closure|array|string $handler,
        string $uri,
        ?string $name = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?int $size = null,
        ?Annotations $annotations = null,
        ?array $icons = null,
        ?array $meta = null,
    ): self
    {
        $this->builder->addResource($handler, $uri, $name, $description, $mimeType, $size, $annotations, $icons, $meta);

        return $this;
    }

    /**
     * Manually registers a resource template handler.
     *
     * @param Handler                   $handler
     * @param array<string, mixed>|null $meta
     */
    public function addResourceTemplate(
        \Closure|array|string $handler,
        string $uriTemplate,
        ?string $name = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?Annotations $annotations = null,
        ?array $meta = null,
    ): self
    {
        $this->builder->addResourceTemplate($handler, $uriTemplate, $name, $description, $mimeType, $annotations, $meta);

        return $this;
    }

    /**
     * Manually registers a prompt handler.
     *
     * @param Handler                   $handler
     * @param ?Icon[]                   $icons
     * @param array<string, mixed>|null $meta
     */
    public function addPrompt(
        \Closure|array|string $handler,
        ?string $name = null,
        ?string $description = null,
        ?array $icons = null,
        ?array $meta = null,
    ): self
    {
        $this->builder->addPrompt($handler, $name, $description, $icons, $meta);

        return $this;
    }

    /**
     * Register a single custom loader.
     */
    public function addLoader(LoaderInterface $loader): self
    {
        $this->builder->addLoader($loader);

        return $this;
    }

    /**
     * @param iterable<LoaderInterface> $loaders
     */
    public function addLoaders(iterable $loaders): self
    {
        $this->builder->addLoaders($loaders);

        return $this;
    }

    /**
     * Builds the fully configured Server instance.
     */
    public function build(): Server
    {
        $toolsDirs = $this->getDiscoveryDirs();
        $this->setDiscovery($this->projectDir, $toolsDirs);
        $this->builder->setInstructions($this->getFinalInstructions());
        return $this->builder->build();
    }
    
    /**
     * Dynamically determines the list of directories to scan depending on the
     * available webservices in iTop
     * 
     * @return string[]
     */
    protected function getDiscoveryDirs(): array
    {
        $dirsToScan = ['/src/Tools/schema'];

        if($this->iTopClient->canConnect()) {
            $listOperationsResponse = $this->restOperationsCache->get('list_operations', function (ItemInterface $item): string {
                $this->mcpLogger->debug("restOperationsCache cache MISS for list_operations");
                $listOperationsResponse = $this->iTopClient->postJsonToItop(json_encode(['operation' => 'list_operations']));
                $decodedResponse = json_decode($listOperationsResponse, true);
                if ($decodedResponse === false) {
                    // Invalid JSON, is it the correct URL to context iTop?
                    $this->mcpLogger->error("Invalid JSON returned from iTop at URL='".$this->iTopClient->getRestEndpointUrl()."'. Is it the correct URL?", ['response_content' => $listOperationsResponse]);
                    $item->expiresAfter(0);
                    throw new \Exception("Error contacting iTop. Check the MCP server logs and adjust the MCP server configuration.");
                } elseif ($decodedResponse['code'] != 0) {
                    // Something went wrong with iTop
                    $this->mcpLogger->error("'list_operations' returned an error: code:'{$decodedResponse['code']}', message: '{$decodedResponse['message']}'.");
                    if ($decodedResponse['code'] == 1) {
                        $this->mcpLogger->error("Check that your token is correct and that 'Token' is enabled in 'allowed_login_types' in the iTop configuration file.");
                    }
                    $item->expiresAfter(0);
                    throw new \Exception("Error contacting iTop. Check the MCP server logs and adjust the MCP server configuration.");
                }
                $item->expiresAfter(300);
                return $listOperationsResponse;
            }, 1.0);
            $decodedResponse = json_decode($listOperationsResponse, true);
            foreach($decodedResponse['operations'] as $operation) {
                $dir = '/src/Tools/'.$operation['verb'];
                if (is_dir($this->projectDir.$dir)) {
                    $dirsToScan[] = $dir;
                }
            }
            $this->mcpLogger->debug("iTopBuilder::getDiscoveryDirs", ['dirsToScan' => $dirsToScan]);
        }
        return $dirsToScan;
    }
}
