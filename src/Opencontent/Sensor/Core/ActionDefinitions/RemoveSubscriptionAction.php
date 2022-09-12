<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Subscription;
use Opencontent\Sensor\Api\Values\User;

class RemoveSubscriptionAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'unsubscribe';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_subscribe');
        $this->inputName = 'Unsubscribe';
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $subscription = $repository->getSubscriptionService()->getUserSubscription($user, $post);
        if ($subscription instanceof Subscription) {

            $repository->getSubscriptionService()->removeSubscription($subscription);

            $auditStruct = new AuditStruct();
            $auditStruct->createdDateTime = new \DateTime();
            $auditStruct->creator = $user;
            $auditStruct->post = $post;
            $auditStruct->text = "Rimossa sottoscrizione";
            $repository->getMessageService()->createAudit($auditStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, ['subscription' => $subscription->id]);
        }
    }
}