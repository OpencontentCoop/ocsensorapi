<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Legacy\Repository;
use Psr\Log\LoggerInterface;

class SendMailListener extends AbstractListener
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {
            if ($param->identifier == 'after_run_action' && $param->parameters['is_main']) {
                $queue = MailNotificationListener::getQueue();
                foreach ($queue as $mail) {
                    if (!\eZMailTransport::send($mail)) {
                        $this->repository->getLogger()->error("Fail sending", ['subject' => $mail->Subject, 'receivers' => array_column($mail->ReceiverElements, 'email')]);
                    }else{
                        $this->repository->getLogger()->info("Sent mail", ['subject' => $mail->Subject, 'receivers' => array_column($mail->ReceiverElements, 'email')]);
                    }
                }
                MailNotificationListener::clearQueue();
            }
        }
    }

}