<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 01/12/15
 * Time: 18:41
 */

namespace OpenContent\Sensor\Core;

use OpenContent\Sensor\Api\EventService as EventServiceInterface;

abstract class EventService implements EventServiceInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @param Repository $repository
     */
    public function __construct( Repository $repository )
    {
        $this->repository = $repository;
    }
}