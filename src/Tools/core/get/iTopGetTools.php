<?php
declare(strict_types=1);

namespace App\Tools\core\get;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Twig\Environment;
use App\Service\iTopClientInterface;
use App\Service\DatamodelService;
use Psr\Log\LoggerInterface;
use App\Tools\iTopRestTools;

class iTopGetTools extends iTopRestTools
{
    private DatamodelService $datamodel; 
   
    public function __construct(Environment $twig, iTopClientInterface $iTopClient, LoggerInterface $mcpLogger, DatamodelService $datamodel)
    {
        $this->datamodel = $datamodel;
        parent::__construct($twig, $iTopClient, $mcpLogger);
    }

    /**
     * This tool fetches all the fields of an object in iTop. The object is identified by its class and ID (key)
     * @param string $object_class The class of the object to retrieve
     * @param int $id The identifier (key) of the object to retrieve
     */
    #[McpTool(name: TOOL_PREFIX.'get-object-details-from-id', annotations: new ToolAnnotations(null, true, false, true, false))]
    public function getObjectDetailsFromId(string $object_class, int $object_id): string
    {
        $this->mcpLogger->info('[Tool called] get-object-details-from-id');
        return $this->runToolFromTemplates('getAnythingDetails', 'Anything', ['class' => $object_class, 'key' => $object_id]);
    }
    /**
     * This tool fetches a Person object in iTop from her/his email address
     */
    #[McpTool(name: TOOL_PREFIX.'get-person-from-email', annotations: new ToolAnnotations(null, true, false, true, false))]
    public function getPersonFromEmail(#[Schema(format: 'email')] string $email): string
    {
        $this->mcpLogger->info('[Tool called] get-person-from-email');
        return $this->runToolFromTemplates('getPersonFromEmail', 'Anything', [
            'email' => $email,
            'fields' => $this->datamodel->getListZlist('Person'),
        ]);
    }

    /**
     * This tool fetches a Person object in iTop from her/his full name
     */
    #[McpTool(name: TOOL_PREFIX.'get-person-from-fullname', annotations: new ToolAnnotations(null, true, false, true, false))]
    public function getPersonFromFullname(string $fullname): string
    {
        $this->mcpLogger->info('[Tool called] get-person-from-fullname');
        return $this->runToolFromTemplates('getPersonFromFullname', 'Anything', [
            'fullname' => $fullname,
            'fields' => $this->datamodel->getListZlist('Person'),
        ]);
    }

    /**
     * This tool fetches a Person object in iTop from her/his telephone number
     */
    #[McpTool(name: TOOL_PREFIX.'get-person-from-telephone', annotations: new ToolAnnotations(null, true, false, true, false))]
    public function getPersonFromTelephone(string $telephone): string
    {
        $this->mcpLogger->info('[Tool called] get-person-from-telephone');
        
        $phones = $this->getPhoneFieldsFromPerson();
        if (count($phones) === 0) {
            throw new \Exception('Datamodel issue: there seem to be no phone number attribute on the Person class!');
        }
        $conditions = array_map(function($item) use($telephone) { return "$item = '$telephone'"; }, $phones);
        $whereClause = implode(' OR ', $conditions);
        return $this->runToolFromTemplates('getPersonFromTelephone', 'Anything', [
            'query' => 'SELECT Person WHERE '.$whereClause,
            'fields' => $this->datamodel->getListZlist('Person'),
        ]);
    }
    
    protected function getPhoneFieldsFromPerson(): array
    {
        $phones = [];
        $fields = $this->datamodel->getClassSchema('Person')['fields'] ?? [];
        foreach($fields as $field) {
            if ($field['type'] === 'AttributePhoneNumber') {
                $phones[] = $field['code'];
            }
        }
        return $phones;
    }

    /**
     * This tool searches for existing UserRequests in iTop based on:
     * - the email of the caller
     * - an optional list of statuses for the UserRequest.
     * - an optional start date
     * - if a start date is specified, the condition/operator: either 'greater_than' or 'less_than'
     * @param string $caller_email The email of the caller
     * @param string[] $statuses Optional, a list of status codes (multiple values will be combined with a OR condition). Call get-class-schema('UserRequest') to find the possible values for the status field.
     * @param string $start_date Optional, the start date-time of the UserRequest (format as a MySQL DateTime (YYYY-MM-DD hh:ii:ss)
     * @param string $condition Optional, the condiotnal operator for the start date: either 'greater_than" or 'less_than'
     */
    #[McpTool(name: TOOL_PREFIX.'search-user-request-by-caller-status-start-date', annotations: new ToolAnnotations(null, true, false, true, false))]
    public function searchUserRequestByCallerStatusStartDate(#[Schema(format: 'email')] string $caller_email, array $statuses = [], string $start_date = '', string $condition = 'greater_than'): string
    {
        return $this->runToolFromTemplates('searchUserRequestFromCallerStatusDate', 'UserRequest',
            [
                'caller_email' => $caller_email,
                'statuses' => count($statuses) > 0 ? "'".implode("','", $statuses)."'" : '', 
                'start_date' => $start_date,
                'condition' => $condition === 'greater_than' ? '>=' : '<=',
            ]
       );
    }
}