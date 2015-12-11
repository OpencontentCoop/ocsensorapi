<?php

namespace OpenContent\Sensor\Api\Exception;

use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;

class InvalidParameterException extends BaseException
{
    public function __construct( ActionDefinitionParameter $actionDefinitionParameter )
    {
        $message = "Parameter ({$actionDefinitionParameter->type}) {$actionDefinitionParameter->identifier} is required";
        parent::__construct( $message );
    }
}