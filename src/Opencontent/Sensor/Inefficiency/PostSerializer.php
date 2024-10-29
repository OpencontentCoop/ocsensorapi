<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Stanzadelcittadino\Client\Request\Struct\User;

class PostSerializer
{
    /**
     * @var array
     */
    private $severityMap;

    public function __construct($severityMap = [])
    {
        $this->severityMap = $severityMap;
    }
    
    public function serialize(
        Post $post,
        User $userStruct,
        string $userUuid = null,
        array $images = [],
        array $files = [],
        string $serviceId = "inefficiencies"
    ): array {

        $subject = sprintf('Segnalazione disservizio di %s: %s', $post->author->name, $post->subject);

        $extraData = [
            'submitted_at' => $post->published->format('c'),
            'modified_at' => $post->modified->format('c'),
            'opensegnalazioni_id' => $post->id,
        ];

        $data = [
            "service" => $serviceId,
            "status" => 1900,
            'created_at' => $post->published->format('c'),
            "data" => [
                "application_subject" => $subject,
                "applicant" => [
                    "data" => [
                        "email_address" => $userStruct->email,
                        "phone_number" => $userStruct->cellulare ?? $userStruct->telefono,
                        "completename" => [
                            "data" => [
                                "name" => $userStruct->nome,
                                "surname" => $userStruct->cognome,
                            ],
                        ],
                        "fiscal_code" => [
                            "data" => [
                                "fiscal_code" => $userStruct->codice_fiscale,
                            ],
                        ],
                        "person_identifier" => $userStruct->codice_fiscale,
                    ],
                ],
                "type" => null,
                "severity" => null,
                "details" => $post->description,
                "subject" => $post->subject,
                "meta" => $extraData,
                "sequential_id" => $post->id,
            ],
        ];

        if (is_array($this->severityMap)){
            foreach ($this->severityMap as $severity => $value){
                if ($value === $post->type->identifier){
                    $data['severity'] = $severity;
                    break;
                }
            }
        }

        if (count($post->categories)){
            $data["data"]["type"] = [
                "label" => $post->categories[0]->name,
                "value" => $post->categories[0]->id,
            ];
        }

        $data["data"]["images"] = $images;
        $data["data"]["docs"] = $files;

        if ($post->geoLocation instanceof Post\Field\GeoLocation
            && $post->geoLocation->latitude != 0
            && $post->geoLocation->longitude != 0) {
            $addressDisplayName = $post->geoLocation->address;

            foreach ($post->meta as $key => $value) {
                if (!in_array($key, ['pingback_url', 'application', 'payload', 'links'])) {
                    $address[$key] = $value;
                }
            }

            $data["data"]["address"] = [
                "lat" => $post->geoLocation->latitude,
                "lon" => $post->geoLocation->longitude,
                "display_name" => $addressDisplayName,
                "address" => $address ?? $addressDisplayName,
            ];
        }

        if ($userUuid) {
            $data['user'] = $userUuid;
        }

        return $data;
    }
}