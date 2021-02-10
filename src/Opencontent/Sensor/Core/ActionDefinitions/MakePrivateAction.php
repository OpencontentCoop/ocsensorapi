<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class MakePrivateAction extends ActionDefinition
{
    public $identifier = 'make_private';

    public $permissionDefinitionIdentifiers = array('can_read', 'can_change_privacy');

    public $inputName = 'MakePrivate';

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $repository->getPostService()->setPostStatus($post, 'privacy.private');

        $auditStruct = new AuditStruct();
        $auditStruct->createdDateTime = new \DateTime();
        $auditStruct->creator = $user;
        $auditStruct->post = $post;
        $auditStruct->text = "Impostato a privato";
        $repository->getMessageService()->createAudit($auditStruct);

        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user);
    }
}