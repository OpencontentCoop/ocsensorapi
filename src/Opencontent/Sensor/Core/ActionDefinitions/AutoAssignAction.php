<?php


namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;

class AutoAssignAction extends AssignAction
{
    public function __construct()
    {
        $this->identifier = 'auto_assign';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_auto_assign');
        $this->inputName = 'AutoAssign';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'participant_ids';
        $parameter->isRequired = true;
        $parameter->type = 'array';

        $this->parameterDefinitions[] = $parameter;
    }

    protected function checkParameters(Action $action)
    {
        $action = parent::checkParameters($action);
        $participantIds = (array)$action->getParameterValue('participant_ids');
        if (count($participantIds) !== 1 || $participantIds[0] != \eZUser::currentUserID()){
            throw new InvalidArgumentException("Parameter participant_ids must be only the current user id");
        }

        return $action;
    }

}