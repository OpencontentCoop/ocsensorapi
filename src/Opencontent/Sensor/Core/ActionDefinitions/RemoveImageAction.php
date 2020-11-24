<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\CommentStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;


class RemoveImageAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'remove_image';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_remove_image');
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
            $repository->getPostService()->removeImage($post, $files);

            $commentStruct = new CommentStruct();
            $commentStruct->createdDateTime = new \DateTime();
            $commentStruct->creator = $repository->getCurrentUser();
            $commentStruct->post = $post;
            if (count($files) == 1) {
                $commentStruct->text = 'È stata rimossa un\'immagine (' . basename($files[0]) . ')';
            }else{
                $commentStruct->text = 'Sono stata rimosse immagini (' . implode(', ', array_map('basename', $files )) . ')';
            }
            $repository->getMessageService()->createComment($commentStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('files' => $files));
        }
    }
}