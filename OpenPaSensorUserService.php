<?php

use OpenContent\Sensor\Legacy\UserService as UserServiceBase;

class OpenPaSensorUserService extends UserServiceBase
{
    public function loadUser( $id )
    {
        if ( !isset( $this->users[$id] ) )
        {
            parent::loadUser($id);
            $user = $this->users[$id];
            $user->id = $id;
            $ezUser = eZUser::fetch( $id );
            if ( $ezUser instanceof eZUser )
            {
                $user->email = $ezUser->Email;
                $user->name = $ezUser->contentObject()->name( false, $this->repository->getCurrentLanguage() );
                $user->isEnabled = $ezUser->isEnabled();

                if ( class_exists( 'OCWhatsAppConnector' ) )
                {
                    try
                    {
                        $user->whatsAppId = OCWhatsAppConnector::instanceFromContentObjectId(
                            $user->id
                        )->getUsername();
                    }
                    catch ( Exception $e ){

                    }
                }
            }
        }
        return $this->users[$id];
    }
}