<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Legacy\Repository;

class ApproverFirstReadListener extends AbstractListener
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {
            $user = $param->user;
            $post = $param->post;

            //run addAreaAction
            if (count($post->areas) > 0){
                $action = new Action();
                $action->identifier = 'add_area';
                $action->setParameter('area_id', [$post->areas[0]->id]);
                try {
                    $this->repository->getActionService()->runAction($action, $post);
                }catch (\Exception $e){
                    $this->repository->getLogger()->error($e->getMessage(),  [
                        'event' => $event->getName(),
                        'user' => $user->id,
                        'post' => $post->id
                    ]);
                }
            }

            //run addCategoryAction?
        }
    }

}