<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddAreaAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_area';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_add_area');
        $this->inputName = 'AddArea';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'area_id';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $areaIdList = (array)$action->getParameterValue('area_id');
        $areaNameList = [];
        foreach ($areaIdList as $areaId) {
            try {
                $area = $repository->getAreaService()->loadArea($areaId);
                $areaNameList[] = "#{$areaId} ({$area->name})";
            } catch (NotFoundException $e) {
                throw new InvalidInputException("Item $areaId is not a valid area");
            }
        }

        $areaId = array_shift($areaIdList);

        $isChanged = true;
//        foreach ($post->areas as $area) {
//            if ($area->id == $areaId){
//                $isChanged = false;
//                break;
//            }
//        }

        if ($isChanged) {
            $repository->getPostService()->setPostArea($post, $areaId);

            $auditStruct = new AuditStruct();
            $auditStruct->createdDateTime = new \DateTime();
            $auditStruct->creator = $user;
            $auditStruct->post = $post;
            $auditStruct->text = "Impostata area " . implode(', ', $areaNameList);
            $repository->getMessageService()->createAudit($auditStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('areas' => $areaId));
        }
    }
}
