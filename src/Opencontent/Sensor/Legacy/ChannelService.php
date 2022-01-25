<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Values\Post\Channel;
use Opencontent\Sensor\Core\ChannelService as BaseChannelService;
use Opencontent\Sensor\Legacy\Utils\Translator;

class ChannelService extends BaseChannelService
{
    private $channels;

    public function loadPostChannel($name)
    {
        foreach ($this->loadPostChannels() as $channel) {
            if ($name == $channel->name) {
                return $channel;
            }
        }

        return $this->loadPostDefaultChannel();
    }

    public function loadPostChannels()
    {
        if ($this->channels === null) {
            $channelAttribute = $this->repository->getPostContentClassAttribute('on_behalf_of_mode');
            if ($channelAttribute instanceof \eZContentClassAttribute) {
                /** @var array $typeAttributeContent */
                $channelAttributeContent = $channelAttribute->content();
                foreach ($channelAttributeContent['options'] as $item) {
                    $channel = new Channel();
                    $channel->id = $item['id'];
                    $channel->name = Translator::translate($item['name'], 'channel');
                    $channel->icon = $this->getChannelIcon($item['name']);
                    $this->channels[] = $channel;
                }
            }
        }

        return $this->channels;
    }

    public function loadPostDefaultChannel()
    {
        $channel = new Channel();
        $channel->id = 0;
        $channel->name = 'OpenSegnalazioni';
        $channel->icon = null;

        return $channel;
    }

    private function getChannelIcon($name)
    {
        switch ($name) {
            case 'Telefono':
            case 'Linea verde':
                return 'fa fa-phone';

            case 'Posta cartacea o Fax':
            case 'Posta o Fax':
                return 'fa fa-fax';

            case 'Sportello fisico':
            case 'Front office':
                return 'fa fa-headphones';

            case 'E-mail':
                return 'fa fa-envelope-o';

            case 'App mobile':
                return 'fa fa-mobile';

            case 'Sito web':
            case 'Aiutaci a migliorare':
                return 'fa fa-globe';

            default:
                return 'fa fa-external-link-square';
        }
    }
}
