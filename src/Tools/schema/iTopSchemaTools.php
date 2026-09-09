<?php
declare(strict_types=1);

namespace App\Tools\schema;

use Mcp\Capability\Attribute\McpTool;
use App\Service\DatamodelService;

class iTopSchemaTools
{
    public function __construct(protected DatamodelService $datamodel)
    {
        
    }

    /**
     * This tool lists all the possible classes of objects managed in iTop.
     */
    #[McpTool(name: TOOL_PREFIX.'list-all-classes')]
    public function listAllClasses(): string
    {
        $output = "# Main classes\n\n";
        $classes = $this->datamodel->getClasses();
        ksort($classes);
        foreach($classes as $className => $classInfo) {
            if ((strstr($classInfo['category'], 'bizmodel') !== false) && ($classInfo['is_link'] !== 1)) {
                // Only bizmodel classes which are not n:n links
                $description = $classInfo['description'] === '' ? '' : ': '.$classInfo['description'];
                $inheritance = !in_array($classInfo['parent'], ['DBObject', 'CMDBOject', 'cmdbAbstractObject']) ? ' derived from class '.$classInfo['parent'] : '';
                $output .= "- {$className} (label: {$classInfo['label']}){$inheritance}{$description}\n";
            }
        }
        $output .= "\n# Link classes, used to store many-to-many relationships\n\n";
        foreach($classes as $className => $classInfo) {
            if ($classInfo['is_link'] === 1) {
                // Only bizmodel classes which are not n:n links
                $description = $classInfo['description'] === '' ? '' : ': '.$classInfo['description'];
                $inheritance = !in_array($classInfo['parent'], ['DBObject', 'CMDBOject', 'cmdbAbstractObject']) ? ' derived from class '.$classInfo['parent'] : '';
                $output .= "- {$className} (label: {$classInfo['label']}){$inheritance}{$description}\n";
            }
        }
        return $output;
    }

    /**
     * This tool documents the schema (i.e. the list of all the fields) of a specified class in iTop.
     * @param string $className The name of the class for which to retrieve schema information
     */
    #[McpTool(name: TOOL_PREFIX.'get-class-schema')]
    public function getClassSchema(string $className): string
    {
        $info = $this->datamodel->getClassSchema($className);
        $fields = $info['fields'];
        if ($fields === []) {
            return "The class $className does not exist in iTop. Please provide a valid iTop class name (not a label).";
        }
        
        $output = "Class $className:\n  Fields:\n";
        foreach($fields as $code => $fieldInfo) {
            $type = substr($fieldInfo['type'], 9);
            switch($type) {
                case 'Enum':
                    $type .= ' values: '. implode(' ', array_map(
                        function(string $code, string $label) { return "$code ($label)"; },
                        array_keys($fieldInfo['values']),
                        array_values($fieldInfo['values'])
                    ));
                    break;
                case 'ExternalKey':
                    $type .= " to a {$fieldInfo['target_class']} object";
                    break;
            
                case 'LinkedSet':
                case 'LinkedSetIndirect':
                    $type .= ": a collection of {$fieldInfo['linked_class']} objects";
            }
            $description = $fieldInfo['description'] === '' ? '' : ', '.$fieldInfo['description'];
            $mandatory = $fieldInfo['is_null_allowed'] === 'false' ? ' mandatory ' : '';
            $output .= "    - {$code} ({$fieldInfo['label']}), type: {$type}{$mandatory}{$description}\n";
        }
        $workflow = $info['workflow'];
        if (count($workflow['transitions'] ?? [])) {
            $output .= "\n$className life cycle:\nThe possible values and transitions of the '{$workflow['state_att_code']}' field are the following:\n";
            foreach($workflow['transitions'] as $transition) {
                $output .= "  $transition";
            }
        }
        return $output;
    }
}
