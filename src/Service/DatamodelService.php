<?php
namespace App\Service;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use DOMDocument;
use DOMNode;
use DOMXPath;

class DatamodelService
{

    protected ?DOMXPath $xp;

    public function __construct(private string $datamodelFile, private CacheInterface $datamodelCache, private string $language)
    {
        $this->xp = null;
    }
    
    public function getClasses(): array
    {
        $classes = $this->datamodelCache->get('Classes-'.$this->language, function(ItemInterface $item) {
            $item->expiresAfter(3600);
            if (file_exists($this->datamodelFile)) {
                return $this->getClassesFromXml($this->datamodelFile);
            }
            return [];
        }, 1.0);
        return $classes;
    }
    
    public function getClassSchema(string $className): array
    {
        $classSchema = $this->datamodelCache->get('ClassSchema-'.$className.'-'.$this->language, function(ItemInterface $item) use ($className) {
            $item->expiresAfter(3600);
            if (file_exists($this->datamodelFile)) {
                return $this->getClassSchemaFromXml($className, $this->datamodelFile);
            }
            return [];
        }, 1.0);
        return $classSchema;
    }
    
    public function getClassStateAttCode(string $className): ?string
    {
        $info = $this->getClassSchema($className);
        return $info['workflow']['state_att_code'] ?? null;
    }
    
    public function getListZlist(string $className): string
    {
        $classes = $this->getClasses();
        return $classes[$className]['zlist_list'] ?? 'id,friendlyname';
    }
    
    public function getClassesFromXml(string $xmlFile): array
    {
        $classes = [];
        $document = new DOMDocument();
        $document->load($xmlFile);
        $this->xp = new DOMXPath($document);
        $query = "//classes/class/properties/category/../..";
        $nodes = $this->xp->query($query);
        foreach($nodes as $node) {
            $info = [];
            $info['name'] = $node->getAttribute('id');
            $info['is_link'] = (int)$this->getChildContents('properties/is_link', $node);
            $info['category'] = $this->getChildContents('properties/category', $node);
            $info['abstract'] = $this->getChildContents('properties/abstract', $node) == 'true' ? true : false;
            $info['parent'] = $this->getChildContents('parent', $node);
            $info['label'] = $this->getClassLabel($node->getAttribute('id'), $this->language);
            $info['description'] = $this->getClassDescription($node->getAttribute('id'), $this->language);
            $zlist = $this->getFlatZlist($node->getAttribute('id'), 'list');
            $info['zlist_list'] = $info['is_link'] ? $zlist : 'friendlyname,'.$zlist;
            $classes[$node->getAttribute('id')] = $info;
        }
        return $classes;
    }
    
    public function getClassSchemaFromXml(string $className,string $xmlFile)
    {
        $fields = [];
        $document = new DOMDocument();
        $document->load($xmlFile);
        $this->xp = new DOMXPath($document);
        $query = "/itop_design/classes/class[@id='$className']";
        $nodes = $this->xp->query($query);
        if ($nodes->count() !== 1) {
            throw new \Exception("Class $className not found or ambiguous in the datamodel XML. ".$nodes->count()." elements found.");
        }
        $classNode = $nodes->item(0);
        $inheritedPriorityMatrix = null;
        $parent = $this->getChildContents('parent', $classNode);
        if (!in_array($parent, ['DBObject', 'CMDBOject', 'cmdbAbstractObject'])) {
            // Inherit the fields from the parent class(es)
            $schema = $this->getClassSchema($parent);
            $fields = $schema['fields'];
            $inheritedPriorityMatrix = $schema['priority_matrix'] ?? null;
        }
        $fieldNodes = $this->xp->query("/itop_design/classes/class[@id='$className']/fields/field");
        foreach($fieldNodes as $fieldNode) {
            $fieldInfo = [];
            $fieldInfo['code'] = $fieldNode->getAttribute('id'); 
            $fieldInfo['type'] = $fieldNode->getAttribute('xsi:type');
            $fieldInfo['is_null_allowed'] = $this->getChildContents('is_null_allowed', $fieldNode);
            switch($fieldInfo['type']) {
                case 'AttributeExternalKey':
                    $fieldInfo['target_class'] = $this->getChildContents('target_class', $fieldNode);
                    break;
                case 'AttributeLinkedSet':
                case 'AttributeLinkedSetIndirect':
                    $fieldInfo['linked_class'] = $this->getChildContents('linked_class', $fieldNode);
                    break;
                case 'AttributeEnum':
                    $fieldInfo['values'] = $this->getEnumValues($className, $fieldNode);
            }
            $fieldInfo['label'] = $this->getDictEntry("Class:$className/Attribute:{$fieldInfo['code']}");
            $fieldInfo['description'] = $this->getDictEntry("Class:$className/Attribute:{$fieldInfo['code']}+");
            $fields[$fieldInfo['code']] = $fieldInfo;
        }
        $workflow = $this->getWorkflowfromXml($className);
        // A class that does not redefine ComputePriority() inherits its parent's.
        $priorityMatrix = $this->getPriorityMatrixFromXml($className) ?? $inheritedPriorityMatrix;
        return ['fields' => $fields, 'workflow' => $workflow, 'priority_matrix' => $priorityMatrix];
    }

    /**
     * Reads the impact x urgency -> priority matrix out of the class' ComputePriority() method.
     *
     * In iTop the priority of a ticket is not entered, it is recomputed from impact and urgency on
     * every save (ComputeValues() calls ComputePriority()). The matrix lives as PHP source inside the
     * datamodel, so it can be customized per instance — hence reading it rather than assuming it.
     *
     * This parses the shape shipped by iTop ($aPriorities = array(impact => array(urgency => priority))).
     * A heavily rewritten ComputePriority() will not match, in which case this returns null and callers
     * simply say the priority is computed, without claiming to know how.
     *
     * @return array<int, array<int, int>>|null
     */
    protected function getPriorityMatrixFromXml(string $className): ?array
    {
        $nodes = $this->xp->query("//classes/class[@id='$className']/methods/method[@id='ComputePriority']/code");
        if ($nodes->count() === 0) {
            return null;
        }
        $code = $nodes->item(0)->textContent;

        // Each 'impact => array(urgency => priority, ...)' entry of the outer array.
        if (!preg_match_all('/(\d+)\s*=>\s*(?:array\s*\(|\[)([^)\]]*)(?:\)|\])/s', $code, $matches, PREG_SET_ORDER)) {
            return null;
        }
        $matrix = [];
        foreach ($matches as $match) {
            $impact = (int)$match[1];
            if (preg_match_all('/(\d+)\s*=>\s*(\d+)/', $match[2], $inner, PREG_SET_ORDER)) {
                foreach ($inner as $pair) {
                    $matrix[$impact][(int)$pair[1]] = (int)$pair[2];
                }
            }
        }
        return $matrix === [] ? null : $matrix;
    }

    /**
     * @return array<int, array<int, int>>|null
     */
    public function getPriorityMatrix(string $className): ?array
    {
        $info = $this->getClassSchema($className);
        return $info['priority_matrix'] ?? null;
    }

    protected function getChildContents(string $path, DOMNode $node): string
    {
        $nodes = $this->xp->query($path, $node);
        return ($nodes->item(0) != null) ? $nodes->item(0)->textContent : '';
    }
    
    protected function getClassLabel(string $className, string $language) {
        static $classLabels = null; // memoization
        
        if ($classLabels === null) {
            $query = "//dictionaries/dictionary[@id='".$language."']/entries/entry[starts-with(@id,'Class') and not(contains(@id, '/')) and not(contains(@id, '+'))]";
            $nodes = $this->xp->query($query);
            foreach($nodes as $node) {
                $name = substr($node->getAttribute('id'), 6);
                $classLabels[$name] = $node->textContent;
            }
        }
        return $classLabels[$className] ?? $className;
    }
    
    protected function getClassDescription(string $className, string $language) {
        static $classDescriptions = null; // memoization
        
        if ($classDescriptions === null) {
            $query = "//dictionaries/dictionary[@id='".$language."']/entries/entry[starts-with(@id,'Class') and not(contains(@id, '/')) and contains(@id, '+')]"; // ends-with is a XPath 2.0 function
            $nodes = $this->xp->query($query);
            foreach($nodes as $node) {
                $name = substr(substr($node->getAttribute('id'), 6), 0, -1);
                $classDescriptions[$name] = $node->textContent;
            }
        }
        return $classDescriptions[$className] ?? '';
    }
    
    protected function getEnumValues(string $className, DOMNode $node): array
    {
        $result = [];
        $fieldCode = $node->getAttribute('id');
        $values = $this->xp->query("values/value", $node);
        foreach($values as $value) {
            $code = $this->getChildContents('code', $value);
            $result[$code] = $this->getDictEntry("Class:{$className}/Attribute:{$fieldCode}/Value:{$code}");
        }
        return $result;
    }
    
    protected function getFlatZlist(string $className, string $listName): string
    {
        $items = $this->xp->query("//classes/class[@id='".$className."']/presentation/$listName/items/item");
        $zlist = [];
        foreach($items as $item) {
            $zlist[$item->getAttribute('id')] = $this->getChildContents('rank', $item);
        }

        asort($zlist, SORT_NUMERIC);
        return implode(',', array_keys($zlist));
    }
    
    protected function getDictEntry($id): string
    {
        $items = $this->xp->query("//dictionaries/dictionary[@id='".$this->language."']/entries/entry[@id='$id']");
        if ($items->count() === 0) {
            return '';
        }
        return $items->item(0)->textContent;
    }
    
    protected function getWorkflowfromXml(string $className): array
    {
        $state_attr_nodes = $this->xp->query("//classes/class[@id='".$className."']/properties/fields_semantic/state_attribute");
        if ($state_attr_nodes->count() === 0) {
            return [];
        }
        $state_att_code = $state_attr_nodes->item(0)->textContent;
        $stateNodes = $this->xp->query("//classes/class[@id='".$className."']/lifecycle/states/state");
        $workflow = ['state_att_code' => $state_att_code, 'states' => [], 'transitions' => []];
        foreach($stateNodes as $node) {
            $stateValue = $node->getAttribute('id');
            $workflow['states'][] = $stateValue;
            $targetStateNodes = $this->xp->query("//classes/class[@id='".$className."']/lifecycle/states/state[@id='".$stateValue."']/transitions/transition");
            foreach($targetStateNodes as $targetStateNode) {
                $stimulus = $targetStateNode->getAttribute('id');
                $targetState = $this->getChildContents('target', $targetStateNode);
                $workflow['transitions'][] = "$stateValue -- $stimulus --> $targetState\n";
            }
        }
        return $workflow;
    }
}

