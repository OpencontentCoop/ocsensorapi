<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Legacy\Repository;

class UserGroupPostFixListener extends AbstractListener
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
            if ($post->author->type === 'user' && !empty($post->author->groups)){
                $this->repository->getActionService()->runAction(
                    new Action('close', null, true),
                    $post
                );
            }
        }
    }
}
