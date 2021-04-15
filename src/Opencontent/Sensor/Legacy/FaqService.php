<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Api\Values\Faq;
use Opencontent\Sensor\Core\FaqService as BaseFaqService;

class FaqService extends BaseFaqService
{
    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    protected $faqs = [];

    public function loadFaqs($query, $limit, $cursor)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }

        $searchQuery = $query ? 'q = "' . $query . '"' : '';
        $result = $this->search("$searchQuery sort [name=>asc] limit $limit cursor [$cursor]");
        $items = [];
        foreach ($result->searchHits as $item) {
            $items[$item['metadata']['id']] = $this->internalLoadFaq($item);
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor, 'count' => $result->totalCount];
    }

    /**
     * @param array $content
     * @return Faq
     */
    private function internalLoadFaq(array $content)
    {
        $language = $this->repository->getCurrentLanguage();

        $faq = new Faq();
        $faq->id = (int)$content['metadata']['id'];
        $faq->question = $content['data'][$language]['question'];
        $faq->answer = $content['data'][$language]['answer'];
        if (isset($content['data'][$language]['category'][0]['id'])) {
            $faq->category = (int)$content['data'][$language]['category'][0]['id'];
        }

        return $faq;
    }

    public function createFaq($payload)
    {
        $parentNode = $this->repository->getFaqRootNode();
        if (!$parentNode instanceof \eZContentObjectTreeNode || !$parentNode->canCreate()) {
            throw new ForbiddenException("Current user can not create faq");
        }
        $params = [
            'creator_id' => (int)$this->repository->getCurrentUser()->id,
            'class_identifier' => $this->getClassIdentifierAsString(),
            'parent_node_id' => $parentNode->attribute('node_id'),
            'attributes' => [
                'question' => (string)$payload['question'],
                'answer' => (string)\SQLIContentUtils::getRichContent($payload['answer']),
                'category' => (int)$payload['category'],
                'priority' => isset($payload['priority']) ? (int)$payload['priority'] : 0,
            ]
        ];
        $object = \eZContentFunctions::createAndPublishObject($params);

        return $this->loadFaq($object->attribute('id'));
    }

    public function getClassIdentifierAsString()
    {
        return 'sensor_faq';
    }

    public function loadFaq($faqId)
    {
        if (isset($this->faqs[$faqId])) {
            return $this->faqs[$faqId];
        }
        try {
            $content = $this->searchOne("id = '$faqId'");

            $faq = $this->internalLoadFaq($content);
            $this->faqs[$faqId] = $faq;

            return $faq;
        } catch (\Exception $e) {
            throw new NotFoundException("Faq $faqId not found");
        }
    }

    public function updateFaq(Faq $faq, $payload)
    {
        $contentObject = \eZContentObject::fetch($faq->id);
        if ($contentObject instanceof \eZContentObject) {
            if (!$contentObject->canEdit()) {
                throw new ForbiddenException("Current user can not update faq");
            }
            $attributes = [
                'question' => (string)$payload['question'],
                'answer' => (string)\SQLIContentUtils::getRichContent($payload['answer']),
                'category' => (int)$payload['category'],
                'priority' => isset($payload['priority']) ? (int)$payload['priority'] : 0,
            ];
            if (\eZContentFunctions::updateAndPublishObject($contentObject, ['attributes' => $attributes])) {
                unset($this->faqs[$faq->id]);
                return $this->loadFaq($contentObject->attribute('id'));
            }
        }

        throw new UnexpectedException("Update failed");
    }

    public function removeFaq($faqId)
    {
        //@todo
    }

    public function getSubtreeAsString()
    {
        return $this->repository->getFaqRootNode()->attribute('node_id');
    }
}