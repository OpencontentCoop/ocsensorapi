<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Values\Post\Status;
use Opencontent\Sensor\Core\PostStatusService as BasePostStatusService;

class PostStatusService extends BasePostStatusService
{
    /**
     * @var Repository
     */
    protected $repository;

    private $statuses;

    public function loadPostStatuses()
    {
        if ($this->statuses === null){
            $this->statuses = [];
            $states = $this->repository->getSensorPostStates('sensor');
            foreach ($states as $state){
                $status = new Status();
                $status->identifier = $state->attribute('identifier');
                $status->name = $state->translationByLocale($this->repository->getCurrentLanguage())->attribute('name');
                $status->label = 'info';
                if ($state->attribute('identifier') == 'pending') {
                    $status->label = 'danger';
                } elseif ($state->attribute('identifier') == 'open') {
                    $status->label = 'warning';
                } elseif ($state->attribute('identifier') == 'close') {
                    $status->label = 'success';
                }
                $this->statuses[] = $status;
            }
        }

        return $this->statuses;
    }

    public function loadPostStatus($identifier)
    {
        foreach ($this->loadPostStatuses() as $status){
            if ($status->identifier == $identifier){
                return $status;
            }
        }

        return new Status();
    }

}