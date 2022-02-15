<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\CommentStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;


class RemoveFileAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'remove_file';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_remove_file');
        $this->inputName = 'RemoveAttachment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'files';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $files = (array)$action->getParameterValue('files');
        if (!empty($files)) {
            $repository->getPostService()->removeFile($post, $files);

            $commentStruct = new CommentStruct();
            $commentStruct->createdDateTime = new \DateTime();
            $commentStruct->creator = $repository->getCurrentUser();
            $commentStruct->post = $post;
            if (count($files) == 1) {
                $commentStruct->text = 'È stato rimosso un file (' . basename($files[0]) . ')';
            }else{
                $commentStruct->text = 'Sono stati rimossi file (' . implode(', ', array_map('basename', $files )) . ')';
            }
            $repository->getMessageService()->createComment($commentStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('files' => $files));
        }
    }
}
