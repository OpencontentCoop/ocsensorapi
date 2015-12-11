<?php

namespace OpenContent\Sensor\Api;

use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Api\Values\ParticipantCollection;
use OpenContent\Sensor\Api\Values\Participant\ApproverCollection;
use OpenContent\Sensor\Api\Values\Participant\OwnerCollection;
use OpenContent\Sensor\Api\Values\Participant\ObserverCollection;
use OpenContent\Sensor\Api\Values\Participant\ReporterCollection;
use OpenContent\Sensor\Api\Values\ParticipantRoleCollection;
use OpenContent\Sensor\Api\Values\ParticipantRole;

interface ParticipantService
{
    /**
     * @return ParticipantRoleCollection
     */
    public function loadParticipantRoleCollection();

    /**
     * @param Post $post
     * @param $id
     *
     * @return Participant
     */
    public function loadPostParticipantById( Post $post, $id );

    /**
     * @param Post $post
     * @param $role
     *
     * @return ParticipantCollection
     */
    public function loadPostParticipantsByRole( Post $post, $role );

    /**
     * @param Post $post
     *
     * @return ParticipantCollection
     */
    public function loadPostParticipants( Post $post );

    /**
     * @param Post $post
     * @param int $id
     *
     */
    public function addPostParticipant( Post $post, $id, ParticipantRole $role );

    /**
     * @param Post $post
     * @param int $id
     *
     */
    public function trashPostParticipant( Post $post, $id );

    /**
     * @param Post $post
     * @param int $id
     *
     */
    public function restorePostParticipant( Post $post, $id );

    public function activatePostParticipants( Post $post );

    public function deactivatePostParticipants( Post $post );
}