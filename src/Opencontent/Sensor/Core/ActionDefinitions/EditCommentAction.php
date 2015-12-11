<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Message\CommentStruct;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class EditCommentAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'edit_comment';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_comment' );
        $this->inputName = 'EditComment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'id_text';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $parameter->inputName = 'SensorEditComment';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $idTextArray = $action->getParameterValue( 'id_text' );
        foreach( $idTextArray as $id => $text )
        {
            $commentStruct = new CommentStruct();
            $commentStruct->id = $id;
            $commentStruct->text = $text;
            $commentStruct->creator = $user;
            if ( $message = $repository->getMessageService()->updateComment( $commentStruct ) )
            {
                $this->fireEvent( $repository, $post, $user, array( 'message' => $message ) );
            }
        }

        $repository->getPostService()->refreshPost( $post );
    }
}