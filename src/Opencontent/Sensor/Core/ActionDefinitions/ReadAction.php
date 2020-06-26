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
        if ($post->approvers->getUserById($user->id) instanceof User
            && ($post->workflowStatus->is(Post\WorkflowStatus::WAITING)
                || $post->workflowStatus->is(Post\WorkflowStatus::REOPENED))) {

            $isFirstRead = $post->workflowStatus->is(Post\WorkflowStatus::WAITING);

            $repository->getUserService()->setLastAccessDateTime($user, $post);
            $repository->getPostService()->setPostWorkflowStatus($post, Post\WorkflowStatus::READ);
            $repository->getMessageService()->addTimelineItemByWorkflowStatus($post, Post\WorkflowStatus::READ);
            if ($isFirstRead){
                $this->fireEvent($repository, $post, $user, [], 'on_approver_first_read');
            }
            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user);

        }elseif ($post->participants->getUserById($user->id) instanceof User){

            $repository->getUserService()->setLastAccessDateTime($user, $post);
            $post = $repository->getPostService()->refreshPost($post, false);
            $this->fireEvent($repository, $post, $user);

        }
    }
}

