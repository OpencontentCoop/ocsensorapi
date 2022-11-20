<?php

namespace Opencontent\Sensor\Surreal;

use Opencontent\Sensor\Core\ParticipantService as CoreParticipantService;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;

class ParticipantService extends CoreParticipantService
{

    /**
     * @inheritDoc
     */
    public function loadParticipantRoleCollection()
    {
        // TODO: Implement loadParticipantRoleCollection() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPostParticipantById(Post $post, $id)
    {
        // TODO: Implement loadPostParticipantById() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPostParticipantsByRole(Post $post, $role)
    {
        // TODO: Implement loadPostParticipantsByRole() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPostParticipants(Post $post)
    {
        // TODO: Implement loadPostParticipants() method.
    }

    /**
     * @inheritDoc
     */
    public function addPostParticipant(Post $post, $id, ParticipantRole $role)
    {
        // TODO: Implement addPostParticipant() method.
    }

    /**
     * @inheritDoc
     */
    public function trashPostParticipant(Post $post, $id)
    {
        // TODO: Implement trashPostParticipant() method.
    }

    /**
     * @inheritDoc
     */
    public function removePostParticipant(Post $post, $id)
    {
        // TODO: Implement removePostParticipant() method.
    }

    /**
     * @inheritDoc
     */
    public function restorePostParticipant(Post $post, $id)
    {
        // TODO: Implement restorePostParticipant() method.
    }

    public function activatePostParticipants(Post $post)
    {
        // TODO: Implement activatePostParticipants() method.
    }

    public function deactivatePostParticipants(Post $post)
    {
        // TODO: Implement deactivatePostParticipants() method.
    }
}