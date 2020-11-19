<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class PrivateMailNotificationListener extends MailNotificationListener
{
    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {

            $receiverIdList = $param->parameters['receiver_ids'];
            if (!empty($receiverIdList)) {
                $this->repository->getLogger()->info("Notify '{$param->identifier}' on post {$param->post->id} from user {$param->user->id}", $param->parameters);
                $notificationType = $this->repository->getNotificationService()->getNotificationByIdentifier($param->identifier);
                if ($notificationType) {
                    $roles = $this->repository->getParticipantService()->loadParticipantRoleCollection();
                    /** @var ParticipantRole $role */
                    foreach ($roles as $role) {
                        if (!empty($notificationType->targets[$role->identifier])) {
                            $mailData = $this->buildMailDataToRole($param, $notificationType, $role->identifier);
                            $addresses = [];
                            /** @var Participant $participant */
                            foreach ($this->repository->getParticipantService()->loadPostParticipantsByRole($param->post, $role->identifier) as $participant) {
                                if (in_array($participant->id, $receiverIdList)) {
                                    $addresses = array_merge($addresses, $this->getAddressFromParticipant($participant, $param->identifier, $notificationType->targets[$role->identifier]));
                                }
                            }
                            $addresses = array_unique($addresses);
                            if ($mailData && !empty($addresses)) {
                                if ($this->sendMail($addresses, $mailData['subject'], $mailData['body'], $mailData['parameters'])) {
                                    $this->repository->getLogger()->info("Sent private notification mail to addresses: " . implode(',', $addresses));
                                }
                            }
                        } else {
                            $this->repository->getLogger()->debug("Notification targets not found", ['role' => $role->name, 'notification' => $notificationType->name]);
                        }
                    }
                }
            }
        }
    }
}