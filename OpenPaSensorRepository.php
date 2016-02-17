<?php

use Opencontent\Sensor\Legacy\Repository as CoreRepository;
use Opencontent\Sensor\Api\Exception\BaseException;
use Opencontent\Sensor\Core\PermissionDefinitions;
use Opencontent\Sensor\Core\ActionDefinitions;
use Opencontent\Sensor\Api\Values\Settings;

class OpenPaSensorRepository extends CoreRepository
{
    protected $data = array();

    protected static $instance;

    public static function instance()
    {
        //@todo load from ini
        if (self::$instance === null)
            self::$instance = new static();
        return self::$instance;
    }

    protected function __construct()
    {
        $permissionDefinitions = array();
        $permissionDefinitions[] = new PermissionDefinitions\CanAddArea();
        $permissionDefinitions[] = new PermissionDefinitions\CanAddCategory();
        $permissionDefinitions[] = new PermissionDefinitions\CanAddObserver();
        $permissionDefinitions[] = new PermissionDefinitions\CanAssign();
        $permissionDefinitions[] = new PermissionDefinitions\CanChangePrivacy();
        $permissionDefinitions[] = new PermissionDefinitions\CanClose();
        $permissionDefinitions[] = new PermissionDefinitions\CanComment();
        $permissionDefinitions[] = new PermissionDefinitions\CanFix();
        $permissionDefinitions[] = new PermissionDefinitions\CanForceFix();
        $permissionDefinitions[] = new PermissionDefinitions\CanModerate();
        $permissionDefinitions[] = new PermissionDefinitions\CanRead();
        $permissionDefinitions[] = new PermissionDefinitions\CanRespond();
        $permissionDefinitions[] = new PermissionDefinitions\CanSendPrivateMessage();
        $permissionDefinitions[] = new PermissionDefinitions\CanSetExpiryDays();
        $permissionDefinitions[] = new PermissionDefinitions\CanReopen( $this->getSensorSettings()->get('ApproverCanReopen') );
        $permissionDefinitions[] = new \Opencontent\Sensor\Legacy\PermissionDefinitions\CanEdit();
        $permissionDefinitions[] = new \Opencontent\Sensor\Legacy\PermissionDefinitions\CanRemove();
        $this->setPermissionDefinitions( $permissionDefinitions );

        $actionDefinitions = array();
        $actionDefinitions[] = new ActionDefinitions\AddAreaAction();
        $actionDefinitions[] = new ActionDefinitions\AddCategoryAction();
        $actionDefinitions[] = new ActionDefinitions\AddCommentAction();
        $actionDefinitions[] = new ActionDefinitions\AddObserverAction();
        $actionDefinitions[] = new ActionDefinitions\AssignAction();
        $actionDefinitions[] = new ActionDefinitions\CloseAction();
        $actionDefinitions[] = new ActionDefinitions\EditCommentAction();
        $actionDefinitions[] = new ActionDefinitions\EditPrivateMessageAction();
        $actionDefinitions[] = new ActionDefinitions\FixAction();
        $actionDefinitions[] = new ActionDefinitions\ForceFixAction();
        $actionDefinitions[] = new ActionDefinitions\MakePrivateAction();
        $actionDefinitions[] = new ActionDefinitions\MakePublicAction();
        $actionDefinitions[] = new ActionDefinitions\ModerateAction();
        $actionDefinitions[] = new ActionDefinitions\ReadAction();
        $actionDefinitions[] = new ActionDefinitions\ReopenAction();
        $actionDefinitions[] = new ActionDefinitions\SendPrivateMessageAction();
        $actionDefinitions[] = new ActionDefinitions\SetExpiryAction();
        $this->setActionDefinitions( $actionDefinitions );
    }

    public static function sensorRootRemoteId()
    {
        return OpenPABase::getCurrentSiteaccessIdentifier() . '_openpa_sensor';
    }

    public function getSensorSettings()
    {
        $sensorIni = eZINI::instance( 'ocsensor.ini' )->group( 'SensorConfig' );
        return new Settings( array(
            'AllowMultipleOwner' => isset( $sensorIni['AllowMultipleOwner'] ) ? $sensorIni['AllowMultipleOwner'] == 'enabled' : false,
            'AuthorCanReopen' => isset( $sensorIni['AuthorCanReopen'] ) ? $sensorIni['AuthorCanReopen'] == 'enabled' : false,
            'ApproverCanReopen' => isset( $sensorIni['ApproverCanReopen'] ) ? $sensorIni['ApproverCanReopen'] == 'enabled' : false,
            'UniqueCategoryCount' => isset( $sensorIni['CategoryCount'] ) ? $sensorIni['CategoryCount'] == 'unique' : true,
            'CategoryAutomaticAssign' => isset( $sensorIni['CategoryAutomaticAssign'] ) ? $sensorIni['CategoryAutomaticAssign'] == 'enabled' : false,
            'DefaultPostExpirationDaysInterval' => isset( $sensorIni['DefaultPostExpirationDaysInterval'] ) ? intval( $sensorIni['DefaultPostExpirationDaysInterval'] ) : 15,
            'DefaultPostExpirationDaysLimit' => isset( $sensorIni['DefaultPostExpirationDaysLimit'] ) ? intval( $sensorIni['DefaultPostExpirationDaysLimit']) : 7,
            'TextMaxLength' => isset( $sensorIni['TextMaxLength'] ) ? intval( $sensorIni['TextMaxLength'] ) : 800,
            'UseShortUrl' => isset( $sensorIni['UseShortUrl'] ) ? $sensorIni['UseShortUrl'] == 'enabled' : false,
            'ModerateNewWhatsAppUser' => isset( $sensorIni['ModerateNewWhatsAppUser'] ) ? $sensorIni['ModerateNewWhatsAppUser'] == 'enabled' : true,
            'FilterOperatorsByOwner' => isset( $sensorIni['FilterOperatorsByOwner'] ) ? $sensorIni['FilterOperatorsByOwner'] == 'enabled' : true,
            'FilterObserversByOwner' => isset( $sensorIni['FilterObserversByOwner'] ) ? $sensorIni['FilterObserversByOwner'] == 'enabled' : true,
            'CloseCommentsAfterSeconds' => isset( $sensorIni['CloseCommentsAfterSeconds'] ) ? intval( $sensorIni['CloseCommentsAfterSeconds'] ) : 1814400,
            'MoveMarkerOnSelectArea' => isset( $sensorIni['MoveMarkerOnSelectArea'] ) ? $sensorIni['MoveMarkerOnSelectArea'] == 'enabled' : true
        ) );
    }

    public function getCurrentUser()
    {
        if ( $this->user === null )
            $this->user = $this->getUserService()->loadUser( eZUser::currentUserID() );
        return $this->user;
    }

    public function setCurrentLanguage( $language )
    {
        $this->language = $language;
        if ( $this->language != eZLocale::currentLocaleCode() )
        {
            //@todo
            //$GLOBALS["eZLocaleStringDefault"] = $this->language;
            //@todo svuotare cachce translations?
        }
    }

    public function getCurrentLanguage()
    {
        if ( $this->language === null )
            return eZLocale::currentLocaleCode();

        return $this->language;
    }

    public function getRootNode()
    {
        if ( !isset($this->data['root']) )
            $this->data['root'] = eZContentObject::fetchByRemoteID( self::sensorRootRemoteId() )->attribute( 'main_node' );
        return $this->data['root'];
    }

    public function getOperatorsRootNode()
    {
        if ( !isset($this->data['operators']) )
            $this->data['operators'] = eZContentObject::fetchByRemoteID( self::sensorRootRemoteId() . '_operators' )->attribute( 'main_node' );
        return $this->data['operators'];
    }

    public function getCategoriesRootNode()
    {
        if ( !isset($this->data['categories']) )
            $this->data['categories'] = eZContentObject::fetchByRemoteID( self::sensorRootRemoteId() . '_postcategories' )->attribute( 'main_node' );
        return $this->data['categories'];
    }

    public function getAreasRootNode()
    {
        if ( !isset($this->data['areas']) )
            $this->data['areas'] = $this->getRootNode();
        return $this->data['areas'];
    }

    public function getOperatorContentClass()
    {
        if ( !isset($this->data['operator_class']) )
            $this->data['operator_class'] = eZContentClass::fetchByIdentifier( 'sensor_operator' );
        return $this->data['operator_class'];
    }

    public function getSensorCollaborationHandlerTypeString()
    {
        return 'openpasensor';
    }

    public function getPostRootNode()
    {
        if ( !isset($this->data['posts']) )
            $this->data['posts'] = eZContentObject::fetchByRemoteID( self::sensorRootRemoteId() . '_postcontainer' )->attribute( 'main_node' );
        return $this->data['posts'];
    }

    public function getPostContentClass()
    {
        if ( !isset($this->data['post_class']) )
            $this->data['post_class'] = eZContentClass::fetchByIdentifier( 'sensor_post' );
        return $this->data['post_class'];
    }

    public function getUserRootNode()
    {
        if ( !isset($this->data['users']) )
            $this->data['users'] = eZContentObjectTreeNode::fetch( intval( eZINI::instance()->variable( "UserSettings", "DefaultUserPlacement" ) ) );
        return $this->data['users'];
    }

    public function getSensorPostStates( $identifier )
    {
        if ( !isset( $this->data['states_' . $identifier] ) )
        {
            if ( $identifier == 'sensor' )
            {
                $this->data['states_sensor'] = OpenPABase::initStateGroup(
                    'sensor',
                    array(
                        'pending' => "Inviato",
                        'open' => "In carico",
                        'close' => "Chiusa"
                    )
                );
            }
            elseif ( $identifier == 'privacy' )
            {
                $this->data['states_privacy'] = OpenPABase::initStateGroup(
                    'privacy',
                    array(
                        'public' => "Pubblico",
                        'private' => "Privato",
                    )
                );
            }
            elseif ( $identifier == 'moderation' )
            {
                $this->data['states_moderation'] = OpenPABase::initStateGroup(
                    'moderation',
                    array(
                        'skipped' => "Non necessita di moderazione",
                        'waiting' => "In attesa di moderazione",
                        'accepted' => "Accettato",
                        'refused' => "Rifiutato"
                    )
                );
            }
            else
            {
                throw new BaseException( "Status $identifier not handled" );
            }
        }
        return $this->data['states_' . $identifier];
    }

}
