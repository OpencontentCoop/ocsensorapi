<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post\Field\Category;

interface CategoryService
{
    /**
     * @param $areaId
     * @return Category
     */
    public function loadCategory($categoryId);

    public function loadCategories($query, $limit, $cursor);

    public function createCategory($struct);

    public function updateCategory(Category $category, $struct);

    public function removeCategory($categoryId);

    /**
     * @return Category[]
     */
    public function loadAllCategories();
}