<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Values\Post\Type;
use Opencontent\Sensor\Core\PostTypeService as BasePostTypeService;
use Opencontent\Sensor\Legacy\Utils\Translator;

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
                            $type->name = Translator::translate('suggerimento', 'type');
                            $type->label = 'warning';
                            break;

                        case 'reclamo':
                            $type->name = Translator::translate('reclamo', 'type');
                            $type->label = 'danger';
                            break;

                        case 'segnalazione':
                            $type->name = Translator::translate('segnalazione', 'type');
                            $type->label = 'info';
                            break;

                        default:
                            $type->name = ucfirst(Translator::translate($typeIdentifier, 'type'));
                            $type->label = 'info';
                    }

                    $this->types[] = $type;
                }
            }
        }
        return $this->types;
    }

}
