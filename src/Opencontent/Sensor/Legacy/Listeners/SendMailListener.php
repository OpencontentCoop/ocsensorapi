<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Legacy\Repository;

class SendMailListener extends AbstractListener
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        if (!isset($GLOBALS['eZRequestedModuleParams'])) {
            $GLOBALS['eZRequestedModuleParams'] = array(
                'module_name' => null,
                'function_name' => null,
                'parameters' => null
            );
        }
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {
            if ($param->identifier == 'after_run_action' && $param->parameters['is_main']) {
                $queue = MailNotificationListener::getQueue();
                foreach ($queue as $mail) {
                    $receivers = array_merge(
                        array_column($mail->ReceiverElements, 'email'),
                        array_column($mail->CcElements, 'email'),
                        array_column($mail->BccElements, 'email')
                    );
                    if (!\eZMailTransport::send($mail)) {
                        $this->repository->getLogger()->error("Fail sending", ['subject' => $mail->Subject, 'receivers' => $receivers]);
                    } else {
                        $this->repository->getLogger()->info("Sent mail", ['subject' => $mail->Subject, 'receivers' => $receivers]);
                    }
                }
                MailNotificationListener::clearQueue();
            }
        }
    }

}