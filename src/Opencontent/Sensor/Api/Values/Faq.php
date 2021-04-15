<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\Post\Field\Category;

class Faq extends Exportable
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $question;

    /**
     * @var string
     */
    public $answer;

    /**
     * @var Category
     */
    public $category;
}