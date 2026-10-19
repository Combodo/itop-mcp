<?php
declare(strict_types=1);

namespace App\Tools\core\update;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Twig\Environment;
use App\Service\iTopClientInterface;
use Psr\Log\LoggerInterface;
use App\Tools\iTopRestTools;
use App\Service\DatamodelService;

class iTopUpdateTools extends iTopRestTools
{
    private DatamodelService $datamodel; 
    
    public function __construct(Environment $twig, iTopClientInterface $iTopClient, LoggerInterface $mcpLogger, DatamodelService $datamodel)
    {
        $this->datamodel = $datamodel;
        parent::__construct($twig, $iTopClient, $mcpLogger);
    }

    
    /**
     * This tool updates a User Request in iTop by adding an entry to the log and optionally updating the title or the description
     * @param int $id The identifier of the UserRequest to update
     * @param string $additionalLogEntryHtml An new entry to add to the log of the UserRequest - NOTE the log entry MUST BE formatted in HTML 
     * @param string $title Specify the new title for the UserRequest (leave empty for no change) 
     * @param string $descriptionHtml Specify the new description for the UserRequest (leave empty for no change) - NOTE the descriptipon MUST BE formatted in HTML
     */
    #[McpTool(name: TOOL_PREFIX.'update-user-request', annotations: new ToolAnnotations(null, false, true, false, false))]
    public function updateUserRequest(int $id, string $additionalLogEntryHtml, string $title = '', string $descriptionHtml = ''): string
    {
        $this->mcpLogger->info('[Tool called] update-user-request');
        return $this->runToolFromTemplates('updateUserRequest', 'Anything',
            [
                'id' => $id,
                'title' => $title,
                'description' => $descriptionHtml,
                'log' => $additionalLogEntryHtml,
                'output_fields' => $this->datamodel->getListZlist('UserRequest'),
                'log_field' => 'public_log',
            ]
        );
    }

    /**
     * This tool updates an Incident in iTop by adding an entry to the log and optionally updating the title or the description
     * @param int $id The identifier of the Incident to update
     * @param string $additionalLogEntryHtml An new entry to add to the log of the Incident - NOTE the log entry MUST BE formatted in HTML
     * @param string $title Specify the new title for the Incident (leave empty for no change)
     * @param string $descriptionHtml Specify the new description for the Incident (leave empty for no change) - NOTE the descriptipon MUST BE formatted in HTML
     */
    #[McpTool(name: TOOL_PREFIX.'update-incident', annotations: new ToolAnnotations(null, false, true, false, false))]
    public function updateIncident(int $id, string $additionalLogEntryHtml, string $title = '', string $descriptionHtml = ''): string
    {
        $this->mcpLogger->info('[Tool called] update-incident');
        return $this->runToolFromTemplates('updateIncident', 'Anything',
            [
                'id' => $id,
                'title' => $title,
                'description' => $descriptionHtml,
                'log' => $additionalLogEntryHtml,
                'output_fields' => $this->datamodel->getListZlist('Incident'),
                'log_field' => 'public_log',
            ]
        );
    }

    /**
     * This tool can update any object in iTop.
     * 
     * IMPORTANT: before calling this tool use the tool 'list-all-classes' to determine the list of available classes
     * and 'get-class-schema(<className>)' to obtain information about the schema for a particular class (to get information about the fields, their type and possible values).
     * 
     * @param string $object_class The class of the object to update
     * @param int $id The key of the object to update
     * @param string $fields A JSON formatted object {"field_code":"field_value"} for the fields to update in the object
     */
    #[McpTool(name: TOOL_PREFIX.'update-any-object', annotations: new ToolAnnotations(null, false, true, false, false))]
    public function updateAnyObject(string $object_class, int $id, string $fields_json): string
    {
        $fields = json_decode($fields_json, true);
        if ($fields === false) {
            $error = 'Error: invalid value for the parameter "field_json". Expecting a valid JSON structure {"code":"value"} for each field to populate.';
            $this->mcpLogger->error('[Tool called] update-any-object', ['error' => $error, 'object_class' => $object_class, 'id' => $id, 'fields_json' => $fields_json]);
            return $error;
        }
        $state_att_code = $this->datamodel->getClassStateAttCode($object_class);
        if (($state_att_code !== null) && (array_key_exists($state_att_code, $fields))) {
            $error = "Error: invalid value for the parameter 'field_json'. You cannot update the field '$state_att_code' since this is the state of the '$object_class' class. Use the tool '".TOOL_PREFIX."apply-stimulus-to-any-object' to update the state of the object.";
            $this->mcpLogger->error('[Tool called] update-any-object', ['error' => $error, 'object_class' => $object_class, 'id' => $id, 'fields_json' => $fields_json]);
            return $error;
        }
        $this->mcpLogger->info('[Tool called] update-any-object');
        return $this->runToolFromTemplates('updateAnything', 'Anything',
            [
                'class' => $object_class,
                'id' => $id,
                'fields' => $fields,
                'output_fields' => $this->datamodel->getListZlist($object_class),
            ]
        );
    }
}