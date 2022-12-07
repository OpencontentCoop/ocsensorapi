<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\Operator;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Core\SearchService;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils\MailValidator;

class MailNotificationListener extends AbstractListener
{
    private static $queue = [];

    protected $repository;

    private $addCurrentUserAddress = true;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {
            $this->repository->getLogger()->info("Notify '{$param->identifier}' on post {$param->post->id} from user {$param->user->id}", $param->parameters);

            $notificationType = $this->repository->getNotificationService()->getNotificationByIdentifier($param->identifier);

            $auditMessages = [];
            if ($notificationType) {
                $roles = $this->repository->getParticipantService()->loadParticipantRoleCollection();
                /** @var ParticipantRole $role */
                foreach ($roles as $role) {
                    if (!empty($notificationType->getTarget($role->identifier))) {

                        $localizedAddresses = [];
                        /** @var Participant $participant */
                        foreach ($this->repository->getParticipantService()->loadPostParticipantsByRole($param->post, $role->identifier) as $participant) {
                            $localizedAddresses = array_merge_recursive($localizedAddresses, $this->getLocalizedAddressFromParticipant($participant, $param->identifier, $notificationType->getTarget($role->identifier)));
                        }
                        foreach ($localizedAddresses as $locale => $values){
                            $localizedAddresses[$locale] = array_unique($values);
                        }

                        foreach ($localizedAddresses as $locale => $addresses) {
                            $mailData = $this->buildMailDataToRole($param, $notificationType, $role->identifier, $locale);
                            $this->repository->setCurrentLanguage(\eZLocale::currentLocaleCode());
                            if ($mailData && !empty($addresses)) {
                                if ($this->sendMail($addresses, $mailData['subject'], $mailData['body'], $mailData['parameters'])) {
                                    $this->repository->getLogger()->info("Prepare notification mail to {$role->name} addresses: " . implode(',', $addresses));
                                    $auditMessages[] = "Invio notifica ($locale) '{$notificationType->name}' a utenti con ruolo '{$role->name}': " . implode(', ', $addresses);
                                }
                            }
                        }
                    }else{
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

    private function replacePlaceholders($text, $post)
    {
        if (class_exists('SensorPlaceholderCompiler')){
            return \SensorPlaceholderCompiler::instance()->compileValue($text, $post);
        }
        return $text;
    }

    /**
     * @param SensorEvent $event
     * @param NotificationType $notificationType
     * @param int $roleIdentifier
     * @param string $locale
     * @return array|bool
     */
    public function buildMailDataToRole($event, $notificationType, $roleIdentifier, $locale = null)
    {
        if ($locale){
            $this->repository->setCurrentLanguage($locale);
        }else{
            $locale = $this->repository->getCurrentLanguage();
        }

        try {
            $notificationTexts = $notificationType->template[$roleIdentifier][$locale];
            \eZTemplate::resetInstance();
            $tpl = \eZTemplate::factory();
            $tpl->resetVariables();
            $templateName = $this->getNotificationMailTemplate($roleIdentifier);

            if (!$templateName) {
                $this->repository->getLogger()->error('Mail template not found', ['event' => $event->identifier, 'role' => $roleIdentifier]);

                return false;
            }
            $this->repository->getLogger()->info('Build mail template', ['event' => $event->identifier, 'locale' => $locale, 'template' => $templateName]);

            $templatePath = 'design:sensor/mail/' . $event->identifier . '/' . $templateName;
            $tpl->setVariable('event_details', $event->parameters);
            $tpl->setVariable('body', '');
            $tpl->setVariable('subject', $notificationTexts['title']);
            $tpl->setVariable('header', $this->replacePlaceholders($notificationTexts['header'], $event->post));
            $tpl->setVariable('text', $this->replacePlaceholders($notificationTexts['text'], $event->post));
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
                if ($tpl->hasVariable('from')) {
                    $mailParameters['from'] = $tpl->variable('from');
                }
                if ($tpl->hasVariable('content_type')) {
                    $mailParameters['content_type'] = $tpl->variable('content_type');
                } else {
                    $mailParameters['content_type'] = 'text/html';
                }
//                if ($tpl->hasVariable('message_id')) {
//                    $mailParameters['message_id'] = $tpl->variable('message_id');
//                }
//                if ($tpl->hasVariable('reply_to')) {
//                    $mailParameters['reply_to'] = $tpl->variable('reply_to');
//                }

                //$mailParameters['sensor_post_id'] = $event->post->id;
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

    protected function getNotificationMailTemplate($participantRole)
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

    protected function getLocalizedAddressFromParticipant(Participant $participant, $notificationIdentifier, $targets)
    {
        $addresses = [];
        if ($participant->type == Participant::TYPE_USER && in_array(Participant::TYPE_USER, $targets)) {
            foreach ($participant->users as $user) {
                if ($this->repository->getCurrentUser()->id == $user->id && !$this->addCurrentUserAddress){
                    continue;
                }
                $userNotifications = $this->repository->getNotificationService()->getUserNotifications($user);
                if (in_array($notificationIdentifier, $userNotifications) && MailValidator::validate($user->email)) {
                    $addresses[$user->language][] = $user->email;
                }
            }
        } elseif ($participant->type == Participant::TYPE_GROUP && in_array(Participant::TYPE_GROUP, $targets)) {
            try {
                $group = $this->repository->getGroupService()->loadGroup($participant->id, []);
                if ($group instanceof Group) {
                    if (MailValidator::validate($group->email)) {
                        $addresses[\eZLocale::currentLocaleCode()][] = $group->email;
                    }
                    $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, '*', []);
                    /** @var Operator[] $operators */
                    $operators = $operatorResult['items'];
                    $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
                    foreach ($operators as $operator) {
                        if ($this->repository->getCurrentUser()->id == $operator->id && !$this->addCurrentUserAddress){
                            continue;
                        }
                        $userNotifications = $this->repository->getNotificationService()->getUserNotifications($operator);
                        if (in_array($notificationIdentifier, $userNotifications) && MailValidator::validate($operator->email)) {
                            $addresses[$operator->language][] = $operator->email;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->repository->getLogger()->error($e->getMessage(), ['participant' => $participant->name, 'notification' => $notificationIdentifier]);
            }
        }
        foreach ($addresses as $locale => $values){
            $addresses[$locale] = array_unique($values);
        }
        return $addresses;
    }

    /**
     * @param Group $group
     * @param $operatorResult
     * @param $operators
     * @return Operator[]
     * @throws \Opencontent\Sensor\Api\Exception\InvalidInputException
     */
    private function recursiveLoadOperatorsByGroup(Group $group, $operatorResult, &$operators)
    {
        if ($operatorResult['next']) {
            $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, $operatorResult['next'], []);
            $operators = array_merge($operatorResult['items'], $operators);
            $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
        }

        return $operators;
    }

    protected function sendMail($addresses, $mailSubject, $mailBody, $mailParameters)
    {
        self::$queue[] = $this->prepareMail($addresses, $mailSubject, $mailBody, $mailParameters);

        return true;
    }

    /**
     * @return \eZMail[]
     */
    public static function getQueue()
    {
        return self::$queue;
    }

    public static function clearQueue()
    {
        self::$queue = [];
    }

    private function prepareMail($addressList, $subject, $body, $parameters = array())
    {
        $ini = \eZINI::instance();
        $mail = new \eZMail();
        $addressList = $this->prepareAddressString($addressList, $mail);

        if ($addressList == false) {
            $this->repository->getLogger()->error('Error with receiver');
            return false;
        }

        $notificationINI = \eZINI::instance('notification.ini');
        $emailSender = $notificationINI->variable('MailSettings', 'EmailSender');
        if (!$emailSender)
            $emailSender = $ini->variable('MailSettings', 'EmailSender');
        if (!$emailSender)
            $emailSender = $ini->variable("MailSettings", "AdminEmail");

        foreach ($addressList as $index => $addressItem) {
            $mail->extractEmail($addressItem, $email, $name);
            $mail->addReceiver($email, $name);
        }
        $mail->setSender($emailSender);
        $mail->setSubject($subject);
        $mail->setBody($body);

//        if (isset($parameters['sensor_post_id']))
//            $mail->addExtraHeader('X-Sensor-Post-ID', $parameters['sensor_post_id']);
        if (isset($parameters['message_id'])) {
            $mail->setMessageID($parameters['message_id']);
        }
        if (isset($parameters['references']))
            $mail->addExtraHeader('References', $parameters['references']);
        if (isset($parameters['reply_to']))
            $mail->addExtraHeader('In-Reply-To', $parameters['reply_to']);
        if (isset($parameters['from']))
            $mail->setSenderText($parameters['from']);
        if (isset($parameters['content_type']))
            $mail->setContentType($parameters['content_type']);

        return $mail;
    }

    private function prepareAddressString($addressList, $mail)
    {
        if (is_array($addressList)) {
            $validatedAddressList = array();
            foreach ($addressList as $address) {
                if ($mail->validate($address)) {
                    $validatedAddressList[] = $address;
                }
            }
            return $validatedAddressList;
        } else if (strlen($addressList) > 0) {
            if ($mail->validate($addressList)) {
                return $addressList;
            }
        }
        return false;
    }

}
