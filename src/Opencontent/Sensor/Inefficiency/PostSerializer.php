<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Stanzadelcittadino\Client\Request\Struct\User;

class PostSerializer
{
    public function serialize(
        Post $post,
        User $userStruct,
        string $userUuid = null,
        array $images = [],
        array $files = [],
        string $serviceId = "inefficiencies"
    ): array {
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
                "type" => count($post->categories) > 0 ? $post->categories[0]->id : null,
                "details" => $post->description,
                "subject" => $post->subject,
                "meta" => $extraData,
                "sequential_id" => $post->id,
            ],
        ];

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