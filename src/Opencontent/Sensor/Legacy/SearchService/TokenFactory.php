<?php

namespace Opencontent\Sensor\Legacy\SearchService;

use Opencontent\Opendata\Api\QueryLanguage\EzFind;

class TokenFactory extends EzFind\TokenFactory
{
    public $sensorFieldsMap;

    public function __construct($sensorFieldsMap, $fields, $metaFields, $functionFields, $operators, $parameters, $clauses)
    {
        $this->sensorFieldsMap = $sensorFieldsMap;

        parent::__construct($fields, $metaFields, $functionFields, $operators, $parameters, $clauses);
    }

    protected function findFieldType(\Opencontent\QueryLanguage\Parser\Token $token)
    {
        $string = (string)$token;

        $stringWithWildCard = $this->parseWildcard($string);
        if (isset($this->sensorFieldsMap[$stringWithWildCard])) {
            $tokenFunction = 'raw[' . $this->reverseWildcard($string, $this->sensorFieldsMap[$stringWithWildCard]) . ']';
            $token->setToken($tokenFunction);
            $token->data('is_function_field', true);
            $token->data('function', 'raw');

            return true;
        }

        return parent::findFieldType($token);
    }

    private function findWildcard($string)
    {
        $parts = explode('_', $string);
        foreach ($parts as $index => $value) {
            if (is_numeric($value)) {
                return $value;
            }
        }

        return '';
    }

    private function parseWildcard($string)
    {
        return str_replace($this->findWildcard($string), '*', $string);
    }

    private function reverseWildcard($source, $string)
    {
        return str_replace('*', $this->findWildcard($source), $string);
    }
}