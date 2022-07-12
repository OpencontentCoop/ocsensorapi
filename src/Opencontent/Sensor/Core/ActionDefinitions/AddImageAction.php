<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\CommentStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddImageAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_image';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_add_image');
        $this->inputName = 'AddImage';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'files';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $files = $action->getParameterValue('files');
        if (isset($files['filename'])) { //@todo correggere lo schema
            $files = [$files];
        }

        if (count($files) > 0) {

            $repository->getPostService()->addImage($post, $files);

            $commentStruct = new CommentStruct();
            $commentStruct->createdDateTime = new \DateTime();
            $commentStruct->creator = $repository->getCurrentUser();
            $commentStruct->post = $post;
            //@todo gestire traduzioni
            if (count($files) == 1) {
                $commentStruct->text = 'È stata inserita una nuova immagine (' . $files[0]['filename'] . ')';
            }else{
                $commentStruct->text = 'Sono stata inserite nuove immagini (' . implode(', ', array_column($files, 'filename')) . ')';
            }
            $repository->getMessageService()->createComment($commentStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('text' => $commentStruct->text), 'on_add_comment');
            $this->fireEvent($repository, $post, $user, array('files' => array_column($files, 'filename')));
        }
    }
}