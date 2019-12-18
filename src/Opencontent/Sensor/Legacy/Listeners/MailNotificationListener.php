<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils\MailValidator;

class MailNotificationListener extends AbstractListener
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {
            $this->repository->getLogger()->info("Notify '{$param->identifier}' on post {$param->post->id} from user {$param->user->id}", $param->parameters);

            $notificationType = $this->repository->getNotificationService()->getNotificationByIdentifier($param->identifier);

            if ($notificationType) {
                $roles = $this->repository->getParticipantService()->loadParticipantRoleCollection();
                /** @var ParticipantRole $role */
                foreach ($roles as $role) {
                    $mailData = $this->buildMailDataToRole($param, $notificationType, $role->identifier);
                    $addresses = [];
                    /** @var Participant $participant */
                    foreach ($this->repository->getParticipantService()->loadPostParticipantsByRole($param->post, $role->identifier) as $participant) {
                        $addresses = array_merge($addresses, $this->getAddressFromParticipant($participant, $param->identifier));
                    }
                    $addresses = array_unique($addresses);
                    if ($mailData && !empty($addresses)) {
                        if ($this->sendMail($addresses, $mailData['subject'], $mailData['body'], $mailData['parameters'])) {
                            $this->repository->getLogger()->info("Sent notification mail to {$role->name} addresses: " . implode(',', $addresses));
                        }
                    }
                }
            }
        }
    }

    /**
     * @param SensorEvent $event
     * @param NotificationType $notificationType
     * @param int $roleIdentifier
     * @return array|bool
     */
    public function buildMailDataToRole($event, $notificationType, $roleIdentifier)
    {
        try {
            $currentLanguage = \eZLocale::currentLocaleCode();
            $notificationTexts = $notificationType->template[$roleIdentifier][$currentLanguage];

            $tpl = \eZTemplate::factory();
            $tpl->resetVariables();
            $templateName = $this->getNotificationMailTemplate($roleIdentifier);

            if (!$templateName) {
                $this->repository->getLogger()->error('Mail template not found', ['event' => $event->identifier, 'role' => $roleIdentifier]);

                return false;
            }

            $templatePath = 'design:sensor/mail/' . $event->identifier . '/' . $templateName;
            $tpl->setVariable('event_details', $event->parameters);
            $tpl->setVariable('body', '');
            $tpl->setVariable('subject', $notificationTexts['title']);
            $tpl->setVariable('header', $notificationTexts['header']);
            $tpl->setVariable('text', $notificationTexts['text']);
            $tpl->setVariable('object', $this->repository->getPostService()->getContentObject($event->post));
            $tpl->setVariable('node', $this->repository->getPostService()->getContentObject($event->post)->attribute('main_node'));
            $tpl->fetch($templatePath);

            $body = trim($tpl->variable('body'));
            $mailSubject = $tpl->variable('subject');
            if ($body != '') {
                $tpl->setVariable('title', $mailSubject);
                $tpl->setVariable('content', $body);
                $mailBody = $tpl->fetch('design:mail/sensor_mail_pagelayout.tpl');
                $mailParameters = [];

                if ($tpl->hasVariable('references')) {
                    $mailParameters['references'] = $tpl->variable('references');
                }
                if ($tpl->hasVariable('reply_to')) {
                    $mailParameters['reply_to'] = $tpl->variable('reply_to');
                }
                if ($tpl->hasVariable('from')) {
                    $mailParameters['from'] = $tpl->variable('from');
                }
                if ($tpl->hasVariable('content_type')) {
                    $mailParameters['content_type'] = $tpl->variable('content_type');
                } else {
                    $mailParameters['content_type'] = 'text/html';
                }

                return [
                    'subject' => $mailSubject,
                    'body' => $mailBody,
                    'parameters' => $mailParameters
                ];
            } else {
                $this->repository->getLogger()->error('Empty template result', ['event' => $event->identifier, 'role' => $roleIdentifier]);

                return false;
            }
        } catch (\Exception $e) {
            $this->repository->getLogger()->error($e->getMessage(), ['event' => $event->identifier, 'role' => $roleIdentifier]);

            return false;
        }
    }

    private function getAddressFromParticipant(Participant $participant, $notificationIdentifier)
    {
        $addresses = [];
        if ($participant->type == 'user') {
            foreach ($participant->users as $user) {
                $userNotifications = $this->repository->getNotificationService()->getUserNotifications($user);
                if (in_array($notificationIdentifier, $userNotifications) && MailValidator::validate($user->email)) {
                    $addresses[] = $user->email;
                }
            }
        }elseif ($participant->type == 'group') {
            try {
                $group = $this->repository->getGroupService()->loadGroup($participant->id);
                if ($group instanceof Group && MailValidator::validate($group->email)) {
                    $addresses[] = $group->email;
                }
            }catch (\Exception $e){
                $this->repository->getLogger()->error($e->getMessage(), ['participant' => $participant->name, 'notification' => $notificationIdentifier]);
            }
        }

        return $addresses;
    }

    private function sendMail($addresses, $mailSubject, $mailBody, $mailParameters)
    {
        /** @var \eZMailNotificationTransport $transport */
        $transport = \eZNotificationTransport::instance('ezmail');
        return $transport->send(
            $addresses,
            $mailSubject,
            $mailBody,
            null,
            $mailParameters
        );

    }

    private function getNotificationMailTemplate($participantRole)
    {
        if ($participantRole == ParticipantRole::ROLE_APPROVER) {

            return 'approver.tpl';
        } else if ($participantRole == ParticipantRole::ROLE_AUTHOR) {

            return 'author.tpl';
        } else if ($participantRole == ParticipantRole::ROLE_OBSERVER) {

            return 'observer.tpl';
        } else if ($participantRole == ParticipantRole::ROLE_OWNER) {

            return 'owner.tpl';
        }

        return false;
    }

}