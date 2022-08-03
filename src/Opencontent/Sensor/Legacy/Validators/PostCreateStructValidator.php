<?php

namespace Opencontent\Sensor\Legacy\Validators;

use Opencontent\Sensor\Api\Exception\DuplicateUuidException;
use Opencontent\Sensor\Api\Validators\PostCreateStructValidator as BasePostCreateStructValidator;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Values\Event;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Legacy\UserService;

class PostCreateStructValidator extends BasePostCreateStructValidator
{
    /**
     * @var Repository
     */
    protected $repository;

    public function validate(PostCreateStruct $createStruct)
    {
        if (!$this->repository->getPostRootNode()->canCreate()) {
            throw new ForbiddenException("Current user can not create post");
        }

        parent::validate($createStruct);

        if (is_string($createStruct->uuid) && !empty($createStruct->uuid)){
            try{
                $post = $this->repository->getPostService()->loadPostByUuid($createStruct->uuid);
                if ($post instanceof Post){
                    $duplicateException = new DuplicateUuidException($createStruct->uuid);
                    $duplicateException->setPost($post);
                    throw $duplicateException;
                }
            }catch (NotFoundException $e){}
        }

        /** @var \eZContentClassAttribute $typeClassAttribute */
        $typeClassAttribute = $this->repository->getPostContentClassAttribute('type');
        $typeClassAttributeContent = $typeClassAttribute->content();

        if (!in_array($createStruct->type, array_column($typeClassAttributeContent['options'], 'name'))) {
            throw new InvalidInputException("Type {$createStruct->type} is invalid");
        }

        $privacyStates = $this->repository->getSensorPostStates('privacy');
        if (!isset($privacyStates['privacy.' . $createStruct->privacy])) {
            throw new InvalidInputException("Privacy {$createStruct->privacy} is invalid");
        }

        $area = false;
        if (!empty($createStruct->areas)) {
            foreach ($createStruct->areas as $areaId) {
                try {
                    $area = $this->repository->getAreaService()->loadArea((int)$areaId);
                } catch (NotFoundException $e) {
                    throw new InvalidInputException("Area {$areaId} is invalid");
                }
            }
        }
        if ($createStruct->geoLocation instanceof Post\Field\GeoLocation){
            $areaByGeolocation = $this->repository->getAreaService()->findAreaByGeoLocation($createStruct->geoLocation);
            if ($areaByGeolocation instanceof Post\Field\Area){
                if ($area instanceof Post\Field\Area && $area->id != $areaByGeolocation->id){
                    throw new InvalidInputException("Area {$area->id} is not consistent with the imputed geolocation");
                }
            }elseif ($this->repository->getSensorSettings()->get('MarkerMustBeInArea')){
                throw new InvalidInputException($this->repository->getSensorSettings()->get('MarkerOutOfBoundsAlert'));
            }
        }

        if (!empty($createStruct->categories)) {
            foreach ($createStruct->categories as $categoryId) {
                if (!$this->repository->getCategoryService()->loadCategory((int)$categoryId)) {
                    throw new InvalidInputException("Category {$categoryId} is invalid");
                }
            }
        }

        if (!empty($createStruct->author)) {
            if ($this->repository->getCurrentUser()->behalfOfMode !== true) {
                throw new InvalidInputException("The current user can not post on behalf of others");
            }
            if (\eZMail::validate($createStruct->author)){
                $user = \eZUser::fetchByEmail($createStruct->author);
                if ($user instanceof \eZUser){
                    $createStruct->author = $user->id();
                }else{
                    $author = $this->repository->getUserService()->createUser([
                        'name' => $createStruct->author,
                        'user_type' => UserService::USER_TYPES[0],
                        'email' => $createStruct->author,
                        'fiscal_code' => '',
                    ], true);
                    $createStruct->author = $author->id;

                    $event = new Event();
                    $event->identifier = 'on_generate_user';
                    $event->post = new Post();
                    $event->user = $author;
                    $this->repository->getEventService()->fire($event);
                }
            }
            if (!$this->repository->getUserService()->loadUser($createStruct->author)) {
                throw new InvalidInputException("Author {$createStruct->author} is invalid");
            }
        }
    }

}