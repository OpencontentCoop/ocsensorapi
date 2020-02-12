<?php

namespace Opencontent\Sensor\Api\Values\Post;

use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Exportable;

/**
 * Class WorkflowStatus
 * @package Opencontent\Sensor\Api\Values\Post
 */
class WorkflowStatus extends Exportable
{
    const WAITING = 0;

    const READ = 1;

    const ASSIGNED = 2;

    const CLOSED = 3;

    const FIXED = 4;

    const REOPENED = 6;

    /**
     * @var integer
     */
    public $code;

    /**
     *
     * @var string
     */
    public $identifier;

    public static function instanceByCode($code)
    {
        $instance = new self();
        $instance->code = intval($code);
        $instance->identifier = $instance->getIdentifierByCode($code);
        return $instance;
    }

    /**
     * @param $identifier
     * @return WorkflowStatus
     * @throws InvalidArgumentException
     */
    public static function instanceByIdentifier($identifier)
    {
        switch ($code) {
            case 'waiting';
                return self::instanceByCode(self::WAITING);

            case 'read';
                return self::instanceByCode(self::READ);

            case 'assigned';
                return self::instanceByCode(self::ASSIGNED);

            case 'closed';
                return self::instanceByCode(self::CLOSED);

            case 'fixed';
                return self::instanceByCode(self::FIXED);

            case 'reopened';
                return self::instanceByCode(self::REOPENED);

        }

        throw new InvalidArgumentException("Unknow $identifier workflow status");
    }

    protected function getIdentifierByCode($code)
    {
        switch ($code) {
            case self::WAITING:
                return 'waiting';

            case self::READ:
                return 'read';

            case self::ASSIGNED:
                return 'assigned';

            case self::CLOSED:
                return 'closed';

            case self::FIXED:
                return 'fixed';

            case self::REOPENED:
                return 'reopened';

            default:
                return 'unknown';
        }
    }

    public function is($identifier)
    {
        return $this->identifier === $identifier || $this->code === $identifier;
    }
}