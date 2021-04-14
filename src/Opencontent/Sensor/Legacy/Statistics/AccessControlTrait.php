<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\Exception\ForbiddenException;

trait AccessControlTrait
{
    protected $authorFiscalCode;

    /**
     * @param $authorFiscalCode
     * @throws ForbiddenException
     */
    public function setAuthorFiscalCode($authorFiscalCode)
    {
        $userCanReadSingleUserStat = \eZUser::currentUser()->hasAccessTo('sensor', 'behalf');
        if ($userCanReadSingleUserStat['accessWord'] !== 'yes'){
            throw new ForbiddenException();
        }
        $this->authorFiscalCode = $authorFiscalCode;
    }
}