<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Values\Post\Field;
use eZContentObjectAttribute;
use OCMultiBinaryType;
use eZClusterFileHandler;
use eZClusterFileHandlerInterface;

class BinarySerializer
{
    /**
     * @param Field\Image|Field\File $file
     * @return array
     */
    public function serialize(Field $file): array
    {
        $fileHandler = $this->getClusterFileHandler($file->apiUrl);
        $fileHandler->fetch();
        $upload = [
            'name' => $file->fileName ?? $file->filename,
            'original_filename' => $file->fileName ?? $file->filename,
            'size' => $file->size,
            'protocol_required' => false,
            'mime_type' => $fileHandler->dataType(),
            'path' => $fileHandler->filePath
        ];
        
        return $upload;
    }

    private function getClusterFileHandler($apiUrl): eZClusterFileHandlerInterface
    {
        $parts = explode('/', $apiUrl);
        $attributeIdentifier = $parts[3];
        $fileName = base64_decode($parts[4]);
        [$id, $version, $language] = explode('-', $attributeIdentifier, 3);
        $attribute = eZContentObjectAttribute::fetch($id, $version, $language);

        if ($attribute instanceof eZContentObjectAttribute) {
            if ($attribute->attribute('data_type_string') == OCMultiBinaryType::DATA_TYPE_STRING) {
                $fileInfo = OCMultiBinaryType::storedSingleFileInformation($attribute, $fileName);
            } else {
                $contentObject = $attribute->object();
                $fileInfo = $attribute->storedFileInformation(
                    $contentObject,
                    $contentObject->attribute('current_version'),
                    $attribute->attribute('language_code')
                );
            }
            $fileHandler = eZClusterFileHandler::instance($fileInfo['filepath']);
            if (!$fileHandler->exists()){
                throw new \RuntimeException(sprintf("File path %s not found", $fileInfo['filepath']));
            }
            
            return $fileHandler;
        }

        throw new \RuntimeException("File $apiUrl not found");
    }
}