<?php

namespace Opencontent\Sensor\Legacy\Validators;

use Opencontent\Sensor\Api\Validators\PostCreateStructValidator as BasePostCreateStructValidator;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;

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

        if (!empty($createStruct->areas)) {
            foreach ($createStruct->areas as $areaId) {
                try {
                    $this->repository->getAreaService()->loadArea((int)$areaId);
                } catch (NotFoundException $e) {
                    throw new InvalidInputException("Area {$areaId} is invalid");
                }
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
                    $createStruct->author = $this->repository->getUserService()->createUser([
                        'first_name' => $createStruct->author,
                        'last_name' => '',
                        'email' => $createStruct->author,
                        'fiscal_code' => '',
                    ], true)->id;
                }
            }
            if (!$this->repository->getUserService()->loadUser($createStruct->author)) {
                throw new InvalidInputException("Author {$createStruct->author} is invalid");
            }
        }
    }

}