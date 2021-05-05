<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\Operator;
use Opencontent\Sensor\Api\Values\ParticipantCollection;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Post\Field\Area;
use Opencontent\Sensor\Api\Values\Post\Field\Category;
use Opencontent\Sensor\Api\Values\Post\Field\Attachment;
use Opencontent\Sensor\Api\Values\User;

class Serializer extends AbstractSerializer
{
    /**
     * @var AbstractSerializer[]
     */
    private $serializers;

    public function serialize($item, array $parameters = [])
    {
        $this->loadSerializers();
        $serialized = [];

        if ($item instanceof Post) {
            $serialized = $this->serializers['post']->serialize($item, $parameters);
        } elseif ($item instanceof Message) {
            $serialized = $this->serializers['message']->serialize($item, $parameters);
        } elseif ($item instanceof Attachment) {
            $serialized = $this->serializers['attachment']->serialize($item, $parameters);
        } elseif ($item instanceof Area || $item instanceof Category) {
            $serialized = $this->serializers['area']->serialize($item, $parameters);
        } elseif ($item instanceof ParticipantCollection) {
            $serialized = $this->serializers['participants']->serialize($item, $parameters);
        } elseif ($item instanceof Operator) {
            $serialized = $this->serializers['operator']->serialize($item, $parameters);
        } elseif ($item instanceof User) {
            $serialized = $this->serializers['user']->serialize($item, $parameters);
        }

        return $this->handleEmbed($item, $serialized);
    }

    private function loadSerializers()
    {
        if ($this->serializers === null) {
            $this->serializers = [
                'post' => new PostSerializer($this->apiSettings),
                'message' => new MessageSerializer($this->apiSettings),
                'attachment' => new AttachmentSerializer($this->apiSettings),
                'area' => new AreaSerializer($this->apiSettings),
                'participants' => new ParticipantsSerializer($this->apiSettings),
                'operator' => new OperatorSerializer($this->apiSettings),
                'user' => new UserSerializer($this->apiSettings),
            ];
        }
    }

    private function handleEmbed($item, $serialized)
    {
        if ($item instanceof Post) {
            $embedded = [];
            foreach ($this->getEmbedFields() as $embedField) {
                switch ($embedField) {
                    case 'comments':
                        $embedded['comments'] = $this->serializeItems($item->comments);
                        break;
                    case 'privateMessages':
                        $embedded['privateMessages'] = $this->serializeItems($item->privateMessages);
                        break;
                    case 'responses':
                        $embedded['responses'] = $this->serializeItems($item->responses);
                        break;
                    case  'attachments':
                        $embedded['attachments'] = $this->serializeItems($item->attachments);
                        break;
                    case  'timeline':
                        $embedded['timeline'] = $this->serializeItems($item->timelineItems);
                        break;
                    case 'areas':
                        $embedded['areas'] = $this->serializeItems($item->areas);
                        break;
                    case 'categories':
                        $embedded['categories'] = $this->serializeItems($item->categories);
                        break;
                    case 'approvers':
                        $embedded['approvers'] = $this->serialize($item->approvers);
                        break;
                    case 'owners':
                        $embedded['owners'] = $this->serialize($item->owners);
                        break;
                    case 'observers':
                        $embedded['observers'] = $this->serialize($item->observers);
                        break;
                }
            }
            if (!empty($embedded)) {
                $serialized['_embedded'] = $embedded;
            }
        }

        return $serialized;
    }
}