<?php

namespace OpenContent\Sensor\Api\Values\Post;

use OpenContent\Sensor\Api\Exportable;

class WorkflowStatus extends Exportable
{
    const WAITING = 0;

    const READ = 1;

    const ASSIGNED = 2;

    const CLOSED = 3;

    const FIXED = 4;

    const REOPENED = 6;

    public $code;

    public $identifier;

    public static function instanceByCode( $code )
    {
        $instance = new self();
        $instance->code = $code;
        $instance->identifier = $instance->getIdentifierByCode( $code );
        return $instance;
    }

    protected function getIdentifierByCode( $code )
    {
        switch( $code )
        {
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

    public function is( $identifier )
    {
        return $this->identifier === $identifier || $this->code === $identifier;
    }
}