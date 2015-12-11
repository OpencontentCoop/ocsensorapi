<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;


class MakePublicAction extends ActionDefinition
{
    public $identifier = 'make_public';

    public $permissionDefinitionIdentifiers = array( 'can_read', 'can_change_privacy' );

    public $inputName = 'MakePublic';

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $repository->getPostService()->setPostStatus( $post, 'privacy.public' );
        $repository->getPostService()->refreshPost( $post );
        $this->fireEvent( $repository, $post, $user );
    }
}