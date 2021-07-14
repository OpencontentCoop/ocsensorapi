<?php

namespace Opencontent\Sensor\Legacy\Validators;

use Opencontent\Sensor\Api\Validators\PostUpdateStructValidator as BasePostUpdateStructValidator;
use Opencontent\Sensor\Api\Values\PostUpdateStruct;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Values\Post;

class PostUpdateStructValidator extends BasePostUpdateStructValidator
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var \eZContentObject
     */
    protected $contentObject;

    public function __construct(Repository $repository, \eZContentObject $contentObject)
    {
        parent::__construct($repository);
        $this->contentObject = $contentObject;
    }

    public function validate(PostUpdateStruct $updateStruct)
    {
        if (!$this->contentObject instanceof \eZContentObject || !$this->contentObject->canEdit()) {
            throw new ForbiddenException("Current user can not edit post");
        }

        parent::validate($updateStruct);

        /** @var \eZContentClassAttribute $typeClassAttribute */
        $typeClassAttribute = $this->repository->getPostContentClassAttribute('type');
        $typeClassAttributeContent = $typeClassAttribute->content();

        if ($updateStruct->type && !in_array($updateStruct->type, array_column($typeClassAttributeContent['options'], 'name'))) {
            throw new InvalidInputException("Type {$updateStruct->type} is invalid");
        }

        $privacyStates = $this->repository->getSensorPostStates('privacy');
        if ($updateStruct->privacy && !isset($privacyStates['privacy.' . $updateStruct->privacy])) {
            throw new InvalidInputException("Privacy {$updateStruct->privacy} is invalid");
        }

        $area = false;
        if (!empty($updateStruct->areas)) {
            foreach ($updateStruct->areas as $areaId) {
                try {
                    $area = $this->repository->getAreaService()->loadArea((int)$areaId);
                } catch (NotFoundException $e) {
                    throw new InvalidInputException("Area {$areaId} is invalid");
                }
            }
        }

        if ($updateStruct->geoLocation instanceof Post\Field\GeoLocation){
            $areaByGeolocation = $this->repository->getAreaService()->findAreaByGeoLocation($updateStruct->geoLocation);
            if ($areaByGeolocation instanceof Post\Field\Area){
                if ($area instanceof Post\Field\Area && $area->id != $areaByGeolocation->id){
                    throw new InvalidInputException("Area {$area->id} is not consistent with the imputed geolocation");
                }
            }elseif ($this->repository->getSensorSettings()->get('MarkerMustBeInArea')){
                throw new InvalidInputException($this->repository->getSensorSettings()->get('MarkerOutOfBoundsAlert'));
            }
        }

        if (!empty($updateStruct->categories)) {
            foreach ($updateStruct->categories as $categoryId) {
                try {
                    $this->repository->getCategoryService()->loadCategory((int)$categoryId);
                } catch (NotFoundException $e) {
                    throw new InvalidInputException("Category {$categoryId} is invalid");
                }
            }
        }

        if ((int)$updateStruct->author > 0) {
            if ($this->repository->getCurrentUser()->behalfOfMode !== true) {
                throw new InvalidInputException("The current user can not post on behalf of others");
            }
            if (\eZMail::validate($updateStruct->author)){
                $user = \eZUser::fetchByEmail($updateStruct->author);
                if ($user instanceof \eZUser){
                    $updateStruct->author = $user->id();
                }else{
                    $updateStruct->author = $this->repository->getUserService()->createUser([
                        'first_name' => $updateStruct->author,
                        'last_name' => '',
                        'email' => $updateStruct->author,
                        'fiscal_code' => '',
                    ], true)->id;
                }
            }
            if (!$this->repository->getUserService()->loadUser($updateStruct->author)) {
                throw new InvalidInputException("Author {$updateStruct->author} is invalid");
            }
        }
    }
}