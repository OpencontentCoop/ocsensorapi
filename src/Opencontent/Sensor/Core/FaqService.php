<?php


namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\FaqService as FaqServiceInterface;

abstract class FaqService implements FaqServiceInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @param Repository $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }
}