<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class ReadAction extends ActionDefinition
{
    public $identifier = 'read';

    public $permissionDefinitionIdentifiers = array('can_read');

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $repository->getUserService()->setLastAccessDateTime($user, $post);
        if ($post->approvers->getUserById($user->id) instanceof User
            && ($post->workflowStatus->is(Post\WorkflowStatus::WAITING)
                || $post->workflowStatus->is(Post\WorkflowStatus::REOPENED))) {
            $repository->getPostService()->setPostWorkflowStatus($post, Post\WorkflowStatus::READ);
            $repository->getMessageService()->addTimelineItemByWorkflowStatus($post, Post\WorkflowStatus::READ);
            $post = $repository->getPostService()->refreshPost($post);
        }

        $this->fireEvent($repository, $post, $user);
    }
}

