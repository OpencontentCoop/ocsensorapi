<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Faq;

interface FaqService
{
    public function loadFaq($faqId);

    public function loadFaqs($query, $limit, $cursor);

    public function createFaq($struct);

    public function updateFaq(Faq $faq, $struct);

    public function removeFaq($faqId);
}