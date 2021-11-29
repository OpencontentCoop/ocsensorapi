<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils\MailValidator;

class ReminderNotificationListener extends AbstractListener
{
    const INACTIVITY_INTERVAL_ATTRIBUTE = 'user_inactivity_days';
    const CAMPAIN_ATTRIBUTE = 'reminder_campain';

    const LAST_NOTIFICATION_TIMESTAMP = 'sensor_last_notification_timestamp';

    private $repository;

    private $closedPostsInInterval;

    private $categoryWithMorePostsInInterval;

    private $newUserCountInInterval;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event)
    {
        $notificationType = $this->repository->getNotificationService()->getNotificationByIdentifier($event->getName());
        if ($notificationType) {
            $rootNodeDataMap = $this->repository->getRootNode()->dataMap();
            if (isset($rootNodeDataMap[self::INACTIVITY_INTERVAL_ATTRIBUTE])) {
                if ($rootNodeDataMap[self::INACTIVITY_INTERVAL_ATTRIBUTE]->attribute('data_int') > 0) {
                    $userInactivityDays = $rootNodeDataMap[self::INACTIVITY_INTERVAL_ATTRIBUTE]->attribute('data_int');

                    $campainSuffix = false;
                    if (isset($rootNodeDataMap[self::CAMPAIN_ATTRIBUTE]) && $rootNodeDataMap[self::CAMPAIN_ATTRIBUTE]->hasContent()) {
                        $campainSuffix = $rootNodeDataMap[self::CAMPAIN_ATTRIBUTE]->toString();
                    }

                    $userInactivitySeconds = $userInactivityDays * 86400;
                    $now = time();

                    $users = $this->repository->getNotificationService()->getUsersByNotification($notificationType);
                    foreach ($users as $userId) {
                        $user = $this->repository->getUserService()->loadUser($userId);
                        $eZUser = $this->repository->getUserService()->getEzUser($userId);
                        $lastLogin = (int)$eZUser->lastVisit();

                        $isInactive = ($now - $lastLogin) > $userInactivitySeconds;
                        $lastSent = \eZPreferences::value(self::LAST_NOTIFICATION_TIMESTAMP, $eZUser);
                        $notAlreadySent = $lastSent == false || ($now - $lastSent) > $userInactivitySeconds;

                        if ($isInactive && $notAlreadySent) {
                            $intervalString = \ezfSolrDocumentFieldBase::convertTimestampToDate($now - $lastLogin) . ',' . \ezfSolrDocumentFieldBase::convertTimestampToDate($now);
                            if (!$user) {
                                $this->repository->getLogger()->error("User $userId not found", ['event' => $event->getName()]);
                                continue;
                            }
                            if (!MailValidator::validate($user->email)) {
                                $this->repository->getLogger()->error("Mail of user $userId is not valid", ['event' => $event->getName()]);
                                continue;
                            }
                            $this->repository->getLogger()->info("Notify '{$event->getName()}' to user {$user->name}");

                            $currentLanguage = $this->repository->getUserService()->loadUser($user->id)->language;
                            $notificationTexts = $notificationType->template[ParticipantRole::ROLE_AUTHOR][$currentLanguage];

                            $tpl = \eZTemplate::factory();
                            $tpl->resetVariables();
                            $templatePath = 'design:sensor/mail/reminder.tpl';
                            $tpl->setVariable('body', '');
                            $tpl->setVariable('subject', $notificationTexts['title']);
                            $tpl->setVariable('header', $notificationTexts['header']);
                            $tpl->setVariable('text', $notificationTexts['text']);
                            $tpl->setVariable('campain', $campainSuffix);

                            $tpl->setVariable('last_closed_near_user', $this->getLastClosedPostByUserAddressInInterval($user, $intervalString));
                            $tpl->setVariable('last_closed_user_post', $this->getLastClosedUserPostsInInterval($user, $intervalString));
                            $tpl->setVariable('last_closed_post_count', $this->getLastClosedPostCountInInterval($intervalString));
                            $tpl->setVariable('categories_with_more_posts', $this->getCategoriesWithMorePostsInInterval($intervalString));
                            $tpl->setVariable('new_user_count', $this->getNewUserCountInInterval($intervalString));

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

                                if ($this->sendMail($user->email, $mailSubject, $mailBody, $mailParameters)) {
                                    \eZPreferences::setValue(self::LAST_NOTIFICATION_TIMESTAMP, $now, $user->id);
                                }else{
                                    $this->repository->getLogger()->error('Fail sending mail', ['event' => $event->getName(), 'address' => $user->email, 'error' => error_get_last()['message']]);
                                }
                            } else {
                                $this->repository->getLogger()->error('Empty template result', ['event' => $event->getName()]);
                                continue;
                            }
                        }
                    }
                }
            } else {
                $this->repository->getLogger()->error('Attribute ' . self::INACTIVITY_INTERVAL_ATTRIBUTE . ' in sensor root not found', ['event' => $event->getName()]);
            }
        }

    }

    private function sendMail($address, $subject, $body, $parameters)
    {
        $ini = \eZINI::instance();
        $notificationINI = \eZINI::instance('notification.ini');
        $emailSender = $notificationINI->variable('MailSettings', 'EmailSender');
        if (!$emailSender)
            $emailSender = $ini->variable('MailSettings', 'EmailSender');
        if (!$emailSender)
            $emailSender = $ini->variable("MailSettings", "AdminEmail");


        $mail = new \eZMail();
        $mail->setSender($emailSender);
        $mail->setReceiver($address);
        $mail->setSubject($subject);
        $mail->setBody($body);

        if (isset($parameters['message_id']))
            $mail->addExtraHeader('Message-ID', $parameters['message_id']);
        if (isset($parameters['references']))
            $mail->addExtraHeader('References', $parameters['references']);
        if (isset($parameters['reply_to']))
            $mail->addExtraHeader('In-Reply-To', $parameters['reply_to']);
        if (isset($parameters['from']))
            $mail->setSenderText($parameters['from']);
        if (isset($parameters['content_type']))
            $mail->setContentType($parameters['content_type']);


        return \eZMailTransport::send($mail);
    }

    //@todo spostare in UserService?
    private function getLastClosedPostByUserAddressInInterval(User $user, $intervalString)
    {
        $userObject = $this->repository->getUserService()->getEzUser($user->id)->contentObject();
        if ($userObject instanceof \eZContentObject) {
            $dataMap = $userObject->dataMap();
            if (isset($dataMap['geo']) && $dataMap['geo']->hasContent()) {
                /** @var \eZGmapLocation $geo */
                $geo = $dataMap['geo']->content();
                $latitude = $geo->attribute('latitude');
                $longitude = $geo->attribute('longitude');
                $search = $this->repository->getSearchService()->searchPosts("close range [{$intervalString}] and geosort [{$latitude},{$longitude}] and workflow_status in [closed] limit 1");
                if ($search->totalCount > 0) {
                    return $search->searchHits[0]->jsonSerialize();
                }
            }
        }

        return false;
    }

    private function getLastClosedUserPostsInInterval(User $user, $intervalString)
    {
        return $this->repository->getSearchService()->searchPosts("close range [{$intervalString}] and participant_id_list in [{$user->id}] and workflow_status in [closed]")->jsonSerialize();
    }

    private function getLastClosedPostCountInInterval($intervalString)
    {
        if ($this->closedPostsInInterval === null) {
            $this->closedPostsInInterval = $this->repository->getSearchService()->searchPosts("close range [{$intervalString}] and workflow_status in [closed] limit 1")->totalCount;
        }

        return $this->closedPostsInInterval;
    }

    private function getCategoriesWithMorePostsInInterval($intervalString)
    {
        if ($this->categoryWithMorePostsInInterval === null) {
            $search = $this->repository->getSearchService()->searchPosts("close range [{$intervalString}] facets [category.id|count|5]");
            $data = isset($search->facets[0]['data']) ? $search->facets[0]['data'] : [];
            foreach ($data as $id => $count){
                $object = \eZContentObject::fetch((int)$id);
                if ($object)
                    $this->categoryWithMorePostsInInterval[$object->attribute('name')] = $count;
            }
        }

        return $this->categoryWithMorePostsInInterval;
    }

    private function getNewUserCountInInterval($intervalString)
    {
        if ($this->newUserCountInInterval === null) {
            $this->newUserCountInInterval = $this->repository->getUserService()->search("published range [{$intervalString}] limit 1", [])->totalCount;
        }

        return $this->newUserCountInInterval;
    }
}
