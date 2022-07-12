<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Legacy\Repository;

class SuperUserPostFixListener extends AbstractListener
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent && in_array($param->identifier, ['on_fix'])) {
            $post = $param->post;
            if ($post->author->isSuperUser || $post->reporter->isSuperUser){
                $this->repository->getLogger()->info('Auto-closing a fixed post created by a super user');

                $auditStruct = new AuditStruct();
                $auditStruct->createdDateTime = new \DateTime();
                $auditStruct->creator = $this->repository->getUserService()->loadUser(\eZINI::instance()->variable("UserSettings", "UserCreatorID")); //@todo
                $auditStruct->post = $post;
                $auditStruct->text = "Segnalazione inserita da un utente appartenente a un gruppo di utenti e chiusa automaticamente in base alla configurazione del sistema";
                $this->repository->getMessageService()->createAudit($auditStruct);

                $this->repository->getActionService()->runAction(
                    new Action('close', null, true),
                    $post
                );
            }
        }
    }
}
