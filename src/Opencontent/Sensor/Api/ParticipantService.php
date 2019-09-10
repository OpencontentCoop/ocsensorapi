<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantCollection;
use Opencontent\Sensor\Api\Values\Participant\ApproverCollection;
use Opencontent\Sensor\Api\Values\Participant\OwnerCollection;
use Opencontent\Sensor\Api\Values\Participant\ObserverCollection;
use Opencontent\Sensor\Api\Values\Participant\ReporterCollection;
use Opencontent\Sensor\Api\Values\ParticipantRoleCollection;
use Opencontent\Sensor\Api\Values\ParticipantRole;

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
    public function loadPostParticipantById(Post $post, $id);

    /**
     * @param Post $post
     * @param $role
     *
     * @return ParticipantCollection
     */
    public function loadPostParticipantsByRole(Post $post, $role);

    /**
     * @param Post $post
     *
     * @return ParticipantCollection
     */
    public function loadPostParticipants(Post $post);

    /**
     * @param Post $post
     * @param int $id
     *
     */
    public function addPostParticipant(Post $post, $id, ParticipantRole $role);

    /**
     * @param Post $post
     * @param int $id
     *
     */
    public function trashPostParticipant(Post $post, $id);

    /**
     * @param Post $post
     * @param int $id
     *
     */
    public function restorePostParticipant(Post $post, $id);

    public function activatePostParticipants(Post $post);

    public function deactivatePostParticipants(Post $post);
}