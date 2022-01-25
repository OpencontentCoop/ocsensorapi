<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
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
                $auditMessages = [];
                if ($notificationType) {
                    $roles = $this->repository->getParticipantService()->loadParticipantRoleCollection();
                    /** @var ParticipantRole $role */
                    foreach ($roles as $role) {
                        if (!empty($notificationType->targets[$role->identifier])) {

                            $localizedAddresses = [];
                            /** @var Participant $participant */
                            foreach ($this->repository->getParticipantService()->loadPostParticipantsByRole($param->post, $role->identifier) as $participant) {
                                if (in_array($participant->id, $receiverIdList)) {
                                    $localizedAddresses = array_merge($localizedAddresses, $this->getLocalizedAddressFromParticipant($participant, $param->identifier, $notificationType->targets[$role->identifier]));
                                }
                            }
                            foreach ($localizedAddresses as $locale => $values){
                                $localizedAddresses[$locale] = array_unique($values);
                            }

                            foreach ($localizedAddresses as $locale => $addresses) {
                                $mailData = $this->buildMailDataToRole($param, $notificationType, $role->identifier, $locale);
                                $this->repository->setCurrentLanguage(\eZLocale::currentLocaleCode());
                                if ($mailData && !empty($addresses)) {
                                    if ($this->sendMail($addresses, $mailData['subject'], $mailData['body'], $mailData['parameters'])) {
                                        $this->repository->getLogger()->info("Sent private notification mail to addresses: " . implode(',', $addresses));
                                        $auditMessages[] = "Invio notifica ($locale) privata a " . implode(',', $addresses);
                                    }
                                }
                            }
                        } else {
                            $this->repository->getLogger()->debug("Notification targets not found", ['role' => $role->name, 'notification' => $notificationType->name]);
                        }
                    }
                }
                if (!empty($auditMessages)){
                    $auditStruct = new AuditStruct();
                    $auditStruct->createdDateTime = new \DateTime();
                    $auditStruct->creator = $this->repository->getUserService()->loadUser(\eZINI::instance()->variable("UserSettings", "UserCreatorID")); //@todo
                    $auditStruct->post = $param->post;
                    $auditStruct->text = implode("\n", $auditMessages);
                    $this->repository->getMessageService()->createAudit($auditStruct);
                }
            }
        }
    }
}
