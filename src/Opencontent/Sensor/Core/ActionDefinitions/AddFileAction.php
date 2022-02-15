<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\CommentStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddFileAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_file';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_add_file');
        $this->inputName = 'AddFile';

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

            $repository->getPostService()->addFile($post, $files);

            $commentStruct = new CommentStruct();
            $commentStruct->createdDateTime = new \DateTime();
            $commentStruct->creator = $repository->getCurrentUser();
            $commentStruct->post = $post;
            //@todo gestire traduzioni
            if (count($files) == 1) {
                $commentStruct->text = 'È stato inserito il file (' . $files[0]['filename'] . ')';
            }else{
                $commentStruct->text = 'Sono stati inseriti nuovi file (' . implode(', ', array_column($files, 'filename')) . ')';
            }
            $repository->getMessageService()->createComment($commentStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('files' => array_column($files, 'filename')));
        }
    }
}
