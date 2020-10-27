<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-documentation-apiblueprint for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-documentation-apiblueprint/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-documentation-apiblueprint/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Documentation\ApiBlueprint;

use Laminas\ApiTools\Documentation\Field;
use Laminas\View\Model\ViewModel;

class ApiBlueprintModel extends ViewModel
{

    const FORMAT = '1A9';
    const CODE_BLOCK_INDENT = '        '; // 8 spaces, cannot use tabs (\t)
    const EMPTY_ROW = "\n\n";
    private $verbDescriptions = [
        'POST' => 'Create',
        'PATCH' => 'Update',
        'PUT' => 'Replace',
        'DELETE' => 'Delete',
    ];

    /**
     * @var string
     */
    private $apiBlueprint = '';

    public function terminate()
    {
        return true;
    }

    /**
     * @return  string
     */
    public function getFormattedApiBlueprint($scheme, $host)
    {
        $model = new Api($this->variables['documentation']);
        $this->apiBlueprint = 'FORMAT: ' . self::FORMAT . PHP_EOL;
        $this->apiBlueprint .= 'HOST: ' . $scheme . "://" . $host . self::EMPTY_ROW;
        $this->apiBlueprint .= '# ' . $model->getName() . PHP_EOL;
        if ($model->getDescription()) {
            $this->apiBlueprint .= $model->getDescription() . PHP_EOL;
        }
        $this->apiBlueprint .= $this->writeFormattedResourceGroups($model->getResourceGroups());

        return $this->apiBlueprint;
    }

    /**
     * @param ResourceGroup[] $resourceGroups
     */
    private function writeFormattedResourceGroups(array $resourceGroups)
    {
        foreach ($resourceGroups as $resourceGroup) {
            if ($this->tag && !in_array($this->tag, $resourceGroup->getTags())) {
                continue;
            }

            $this->apiBlueprint .= '# Group ' . $resourceGroup->getName() . PHP_EOL;
            $this->apiBlueprint .= $resourceGroup->getDescription() . PHP_EOL;
            $this->writeFormattedResources($resourceGroup->getResources());
        }
    }

    /**
     * @param Resource[] $resources
     */
    private function writeFormattedResources(array $resources)
    {
        foreach ($resources as $resource) {
            // don't display resources with no actions
            if (count($resource->getActions())) {
                $this->apiBlueprint .= '## ' . $resource->getName() . ' ';
                $this->apiBlueprint .= '[' . $resource->getUri() . ']' . PHP_EOL;
                // if ($resource->getResourceType() !== Resource::RESOURCE_TYPE_COLLECTION) {
                    // $this->writeBodyProperties($resource->getbodyProperties());
                // }
                $this->writeUriParameters($resource);
                $this->writeFormattedActions($resource->getActions(), $resource->getResourceType());
            }
        }
    }

    /**
     * @param Action[] $resources
     */
    private function writeFormattedActions(array $actions, string $resourceType)
    {
        foreach ($actions as $action) {
            $this->apiBlueprint .= '### ' . $action->getDescription() . ' ';
            $this->apiBlueprint .= '[' . $action->getHttpMethod() . ']' . self::EMPTY_ROW;
            $isEntityGetOrDeleteAction = $resourceType === Resource::RESOURCE_TYPE_ENTITY && (
                $action->getHttpMethod() === 'GET' || $action->getHttpMethod() === 'DELETE'
            );
            if (!$isEntityGetOrDeleteAction) {
                $this->writeBodyProperties($action->getBodyProperties());
            }
            $requestDescription = $action->getRequestDescription();
            if ($action->allowsChangingEntity() && ! empty($requestDescription)) {
                $this->apiBlueprint .= '+ Request' . self::EMPTY_ROW;
                $this->apiBlueprint .= self::CODE_BLOCK_INDENT . $this->getFormattedCodeBlock($action->getRequestDescription()) . self::EMPTY_ROW;
            }
            $this->writeFormattedResponses($action);
        }
    }

    /**
     * @param Action $action
     */
    private function writeFormattedResponses(Action $action)
    {
        foreach ($action->getPossibleResponses() as $response) {
            $this->apiBlueprint .= '+ Response ' . $response['code']  . self::EMPTY_ROW;
            if ($response['code'] == 200) {
                $this->apiBlueprint .= self::CODE_BLOCK_INDENT . $this->getFormattedCodeBlock($action->getResponseDescription()) . self::EMPTY_ROW;
            }
            if ($response['code'] >= 400) {
                $problem = new \Laminas\ApiTools\ApiProblem\ApiProblem($response['code'], $response['message']);
                $model = new \Laminas\ApiTools\ApiProblem\View\ApiProblemModel($problem);
                $renderer = new \Laminas\ApiTools\ApiProblem\View\ApiProblemRenderer();
                $this->apiBlueprint .= self::CODE_BLOCK_INDENT . $renderer->render($model).self::EMPTY_ROW;
            }
        }
    }

    /**
     * @param array $bodyProperties
     */
    private function writeBodyProperties(array $bodyProperties)
    {
        $this->apiBlueprint .= '+ Attributes (object)' . PHP_EOL;
        foreach ($bodyProperties as $property) {
            $this->apiBlueprint .= "    + " . $this->getFormattedProperty($property) . PHP_EOL;
        }
        $this->apiBlueprint .= self::EMPTY_ROW;
    }

    /**
     * @var Resource $resource
     */
    private function writeUriParameters(Resource $resource)
    {
        $resourceType = $resource->getResourceType();
        if ($resourceType === Resource::RESOURCE_TYPE_RPC) {
            return;
        }

        $this->apiBlueprint .= '+ Parameters' . PHP_EOL;
        if ($resourceType === Resource::RESOURCE_TYPE_ENTITY) {
            $this->apiBlueprint .= "    + " . $resource->getParameter() . self::EMPTY_ROW;
            return;
        }

        // Laminas API Tools provides pagination results for collections
        // automatically, so page parameter will be available.
        $this->apiBlueprint .= "    + " . 'page (number, optional) - Seek through the results when the number of results exceeds `limit`.' . PHP_EOL;
        $this->apiBlueprint .= "        + " . 'Default: `1`' . PHP_EOL;
        $this->apiBlueprint .= "    + " . 'limit (number, optional) - Number of results per `page`.' . PHP_EOL;
        $this->apiBlueprint .= "        + " . 'Default: `10`' . PHP_EOL;
        $this->apiBlueprint .= "    + " . 'filter (enum[array], optional) - Apply filters on the results by one or more attributes. Learn more about how to use this feature <a href="/api/query">here</a>.' . PHP_EOL;
        $this->apiBlueprint .= "        + Members" . PHP_EOL;
        $this->apiBlueprint .= "            + `type`" . PHP_EOL;
        $this->apiBlueprint .= "            + `field`" . PHP_EOL;
        $this->apiBlueprint .= "            + `value`" . PHP_EOL;
        $this->apiBlueprint .= "            + `alias`" . PHP_EOL;
        $this->apiBlueprint .= "    + " . 'order%2Dby (enum[array], optional) - Sort the results by one or more attributes. Learn more about how to use this feature <a href="/api/query">here</a>.' . PHP_EOL;
        $this->apiBlueprint .= "        + Members" . PHP_EOL;
        $this->apiBlueprint .= "            + `type` (string, required)" . PHP_EOL;
        $this->apiBlueprint .= "            + `field` (string, required)" . PHP_EOL;
        $this->apiBlueprint .= "            + `direction` (string, optional)" . PHP_EOL;

        $this->apiBlueprint .= self::EMPTY_ROW;
    }

    /**
     * @var string $codeBlock
     * @return string
     */
    private function getFormattedCodeBlock($codeBlock)
    {
        return self::CODE_BLOCK_INDENT . str_replace("\n", "\n" . self::CODE_BLOCK_INDENT, $codeBlock);
    }

    /**
     * @var Field $property
     * @return string
     */
    private function getFormattedProperty(Field $property)
    {
        $output = $property->getName();

        if ($property->getExample()) {
            $output .= ': `' . $property->getExample() . '`';
        }

        if (
            $property->getFieldType()
            && (
                $property->getFieldType() === 'int'
                || $property->getFieldType() === 'integer'
            )
        ) {
            $property->setFieldType('number');
        }

        if (
            $property->getFieldType()
            && $property->getFieldType() === 'bool'
        ) {
            $property->setFieldType('boolean');
        }

        if (
            $property->getFieldType()
            && (
                $property->getFieldType() === 'text'
                || $property->getFieldType() === 'datetime'
                || $property->getFieldType() === 'json_array'
            )
        ) {
            $property->setFieldType('string');
        }

        $output .= sprintf(
            ' (%s%s%s)',
            $property->getFieldType(),
            $property->getFieldType() ? ', ' : '',
            $property->isRequired() ? 'required' : 'optional'
        );

        $description = $property->getDescription();
        if (strlen($description)) {
            $output .= ' - ' . $description;
        }

        return $output;
    }
}
