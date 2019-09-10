<?php

namespace Opencontent\Sensor\Legacy\SearchService;

use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\QueryLanguage\EzFind;
use eZINI;

class QueryBuilder extends EzFind\QueryBuilder
{
    public function __construct($sensorPostClassIdentifier)
    {
        $classRepository = new ClassRepository();
        $availableFieldDefinitions = [];
        $class = $classRepository->load($sensorPostClassIdentifier);
        foreach ($class->fields as $field) {
            if ($field['isSearchable']) {
                if (!isset($attributes[$field['identifier']])) {
                    $availableFieldDefinitions[$field['identifier']] = array();
                }

                if (!array_key_exists($field['dataType'], $availableFieldDefinitions[$field['identifier']])) {
                    $availableFieldDefinitions[$field['identifier']][$field['dataType']] = array();
                }

                $availableFieldDefinitions[$field['identifier']][$field['dataType']][] = $class->identifier;
            }
        }
        $this->fields = array_merge(
            $this->fields,
            array_keys($availableFieldDefinitions)
        );

        $filtersList = (array)eZINI::instance('ezfind.ini')->variable('ExtendedAttributeFilters', 'FiltersList');
        $this->parameters = array_merge($this->parameters, array_keys($filtersList));

        $this->tokenFactory = new TokenFactory(
            SolrMapper::getMap(),
            $this->fields,
            $this->metaFields,
            $this->functionFields,
            $this->operators,
            $this->parameters,
            $this->clauses
        );

        $this->solrNamesHelper = new EzFind\SolrNamesHelper($availableFieldDefinitions, $this->tokenFactory);

        $sentenceConverter = new EzFind\SentenceConverter($this->solrNamesHelper);

        $parameterConverter = new EzFind\ParameterConverter($this->solrNamesHelper);

        $this->converter = new EzFind\QueryConverter(
            $sentenceConverter,
            $parameterConverter
        );
    }
}