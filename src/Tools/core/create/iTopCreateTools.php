<?php
declare(strict_types=1);

namespace App\Tools\core\create;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Twig\Environment;
use App\Service\iTopClientInterface;
use Psr\Log\LoggerInterface;
use App\Tools\iTopRestTools;

class iTopCreateTools extends iTopRestTools
{
    public function __construct(Environment $twig, iTopClientInterface $iTopClient, LoggerInterface $mcpLogger)
    {
        parent::__construct($twig, $iTopClient, $mcpLogger);
    }

    /**
     * This tool can create any object in iTop.
     * 
     * IMPORTANT: before calling this tool use the tool 'list-all-classes' to determine the list of available classes
     * and 'get-class-schema(<className>)' to obtain information about the schema for a particular class (to get information about the fields, their type and possible values).
     * 
     * @param string $object_class The class of the object to create
     * @param string $fields A JSON formatted object {"field_code":"field_value"} for the fields to set into the new object
     */
    #[McpTool(name: TOOL_PREFIX.'create-any-object', annotations: new ToolAnnotations(null, false, true, false, false))]
    public function createAnyObject(string $object_class, string $fields_json): string
    {
        $fields = json_decode($fields_json, true);
        if ($fields === false) {
            $error = 'Error: invalid value for the parameter "field_json". Expecting a valid JSON structure {"code":"value"} for each field to populate.';
            $this->mcpLogger->error('[Tool called] create-itop-object', ['error' => $error, 'object_class' => $object_class, 'fields_json' => $fields_json]);
            return $error;
        }
        $this->mcpLogger->info('[Tool called] create-itop-object');
        return $this->runToolFromTemplates('createAnything', 'Anything',
            ['class' => $object_class, 'fields' => $fields]
        );
    }
    
    /**
     * This tool creates a User Request in iTop
     */
    #[McpTool(name: TOOL_PREFIX.'create-user-request', annotations: new ToolAnnotations(null, false, true, false, false))]
    public function createUserRequest(string $title, string $description, int $callerId, int $orgId, int $impact = 3, int $urgency = 4): string
    {
        $this->mcpLogger->info('[Tool called] create-user-request');
        return $this->runToolFromTemplates('createUserRequest', 'UserRequest',
            ['title' => $title, 'description' => $description, 'caller_id' => $callerId, 'org_id' => $orgId, 'impact' => $impact, 'urgency' => $urgency]
        );
    }

    /**
     * This tool creates an Incident in iTop
     */
    #[McpTool(name: TOOL_PREFIX.'create-incident', annotations: new ToolAnnotations(null, false, true, false, false))]
    public function createIncident(string $title, string $description, int $callerId, int $orgId, int $impact = 3, int $urgency = 4): string
    {
        $this->mcpLogger->info('[Tool called] create-incident');
        return $this->runToolFromTemplates('createIncident', 'Incident',
            ['title' => $title, 'description' => $description, 'caller_id' => $callerId, 'org_id' => $orgId, 'impact' => $impact, 'urgency' => $urgency]
        );
    }
}