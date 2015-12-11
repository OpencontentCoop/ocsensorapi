<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;


class MakePrivateAction extends ActionDefinition
{
    public $identifier = 'make_private';

    public $permissionDefinitionIdentifiers = array( 'can_read', 'can_change_privacy' );

    public $inputName = 'MakePrivate';

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $repository->getPostService()->setPostStatus( $post, 'privacy.private' );
        $repository->getPostService()->refreshPost( $post );
        $this->fireEvent( $repository, $post, $user );
    }
}