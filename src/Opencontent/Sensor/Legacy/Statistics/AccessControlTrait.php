<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\Exception\UnauthorizedException;

trait AccessControlTrait
{
    protected $authorFiscalCode;

    /**
     * @param $authorFiscalCode
     * @throws UnauthorizedException
     */
    public function setAuthorFiscalCode($authorFiscalCode)
    {
        $userCanReadSingleUserStat = \eZUser::currentUser()->hasAccessTo('sensor', 'behalf');
        if ($userCanReadSingleUserStat['accessWord'] !== 'yes'){
            throw new UnauthorizedException();
        }
        $this->authorFiscalCode = $authorFiscalCode;
    }
}