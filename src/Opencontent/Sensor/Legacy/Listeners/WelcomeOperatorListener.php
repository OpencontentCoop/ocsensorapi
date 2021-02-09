<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Legacy\Repository;
use eZForgotPassword;
use eZTemplate;
use eZINI;
use eZMail;
use eZMailTransport;
use Opencontent\Sensor\Legacy\Utils\MailValidator;

class WelcomeOperatorListener extends AbstractListener
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent && $param->identifier == 'on_new_operator') {
            $user = $param->user;

            if (!MailValidator::validate($user->email)){
                return;
            }

            $tpl = eZTemplate::factory();
            $tpl->resetVariables();

            $ini = eZINI::instance();

            $time = time();
            $hashKey = md5($time . ":" . mt_rand());
            eZForgotPassword::removeByUserID($user->id);
            $forgotPasswdObj = eZForgotPassword::createNew($user->id, $hashKey, $time);
            $forgotPasswdObj->store();

            $tpl->setVariable('generate_password_link', "/userpaex/forgotpassword/{$hashKey}/");
            $templatePath = 'design:sensor/mail/welcome_operator.tpl';
            $tpl->fetch($templatePath);

            $body = trim($tpl->variable('body'));
            $mailSubject = $tpl->variable('subject');
            if ($body != '') {
                $tpl->setVariable('title', $mailSubject);
                $tpl->setVariable('content', $body);
                $mailBody = $tpl->fetch('design:mail/sensor_mail_pagelayout.tpl');

                $notificationINI = eZINI::instance('notification.ini');
                $mail = new eZMail();

                $emailSender = $notificationINI->variable('MailSettings', 'EmailSender');
                if (!$emailSender) {
                    $emailSender = $ini->variable('MailSettings', 'EmailSender');
                }
                if (!$emailSender) {
                    $emailSender = $ini->variable("MailSettings", "AdminEmail");
                }

                $mail->addReceiver($user->email, $user->name);
                $mail->setSender($emailSender);
                $mail->setSubject($mailSubject);
                $mail->setBody($mailBody);

                if ($tpl->hasVariable('references')) {
                    $mail->addExtraHeader('References', $tpl->variable('references'));
                }
                if ($tpl->hasVariable('reply_to')) {
                    $mail->addExtraHeader('In-Reply-To', $tpl->variable('reply_to'));
                }
                if ($tpl->hasVariable('reply_to')) {
                    $mail->addExtraHeader('References', $tpl->variable('reply_to'));
                }
                if ($tpl->hasVariable('reply_to')) {
                    $mail->setSenderText($tpl->variable('from'));
                }
                if ($tpl->hasVariable('content_type')) {
                    $mail->setContentType($tpl->variable('content_type'));
                } else {
                    $mail->setContentType('text/html');
                }

                if (!eZMailTransport::send($mail)) {
                    $this->repository->getLogger()->error("Fail sending", ['subject' => $mail->Subject, 'receivers' => array_column($mail->ReceiverElements, 'email')]);
                    eZForgotPassword::removeByUserID($user->id);
                } else {
                    $this->repository->getLogger()->info("Sent mail", ['subject' => $mail->Subject, 'receivers' => array_column($mail->ReceiverElements, 'email')]);
                }
            }
        }
    }
}