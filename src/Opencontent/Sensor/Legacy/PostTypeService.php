<?php

namespace Opencontent\Sensor\Legacy;

use ezpI18n;
use Opencontent\Sensor\Api\Values\Post\Type;
use Opencontent\Sensor\Core\PostTypeService as BasePostTypeService;

class PostTypeService extends BasePostTypeService
{
    /**
     * @var Repository
     */
    protected $repository;

    protected $types;

    public function loadPostType($identifier)
    {
        foreach ($this->loadPostTypes() as $type){
            if ($identifier == $type->identifier){
                return $type;
            }
        }

        $type = new Type();
        $type->identifier = $identifier;
        $type->name = ucfirst($identifier);
        $type->label = 'info';

        return $type;
    }

    public function loadPostTypes()
    {
        if ($this->types === null) {
            $this->types = [];
            $typeAttribute = $this->repository->getPostContentClassAttribute('type');
            if ($typeAttribute instanceof \eZContentClassAttribute) {
                /** @var array $typeAttributeContent */
                $typeAttributeContent = $typeAttribute->content();
                foreach ($typeAttributeContent['options'] as $item) {
                    $typeIdentifier = $item['name'];
                    $type = new Type();
                    $type->identifier = $typeIdentifier;
                    switch ($typeIdentifier) {
                        case 'suggerimento':
                            $type->name = ezpI18n::tr('openpa_sensor/type', 'Suggerimento');
                            $type->label = 'warning';
                            break;

                        case 'reclamo':
                            $type->name = ezpI18n::tr('openpa_sensor/type', 'Reclamo');
                            $type->label = 'danger';
                            break;

                        case 'segnalazione':
                            $type->name = ezpI18n::tr('openpa_sensor/type', 'Segnalazione');
                            $type->label = 'info';
                            break;

                        default:
                            $type->name = ucfirst($typeIdentifier);
                            $type->label = 'info';
                    }

                    $this->types[] = $type;
                }
            }
        }
        return $this->types;
    }

}