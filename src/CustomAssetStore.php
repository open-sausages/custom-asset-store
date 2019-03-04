<?php
namespace SilverStripe\CustomAssetsStore;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

/**
 * This class extends `FlysystemAssetStore` so you can match old published file hash
 * (/assets/Uploads/80864e1046/sam.jpg) or legacy style file names (/assets/Uploads/sam.jpg).
 *
 * The latest published version of the file will be return to the browser.
 */
class CustomAssetStore extends FlysystemAssetStore {

    public function getResponseFor($asset)
    {
        /** @var boolean|File $file */
        $file = false;
        $parsedFileID = $this->parseFileID($asset);

        // We skipped this part for authenticated user otherwise, you might not be able to access a protected file
        if (!Security::getCurrentUser() && $parsedFileID && isset($parsedFileID['Hash'])) {
            
            $file = File::get()->filter(['FileFilename' => $parsedFileID['Filename']])->first();

            // If we find a file with the matched Filename, let's look to see if we find a version that matches our Hash
            if ($file) {
                $archivedFile = $file->allVersions(
                    [
                        ['FileHash like ?' => DB::get_conn()->escapeString($parsedFileID['Hash']) . '%'],
                        'WasPublished' => true
                    ],
                    ['ID' => 'Desc'], 1
                )->first();
                // If we found a version that matches our hash, let's return the url to the latest file.
                if ($archivedFile) {
                    $asset = $file->File->getSourceURL();
                    $asset = preg_replace('/^\/?assets\//i', '', $asset);
                }
            }
        } else {
            // Let's try to match the plain file name
            $file = File::get()->filter(['FileFilename' => $asset])->first();
            if ($file) {
                $asset = $file->File->getSourceURL();
                $asset = preg_replace('/^\/?assets\//i', '', $asset);
            }
        }

        return parent::getResponseFor($asset);
    }

    /**
     * Get Filename and Variant from fileid
     *
     * @note This method is identical to the parent implementation, except we return the Hash as well
     * @param string $fileID
     * @return array
     */
    protected function parseFileID($fileID)
    {
        if ($this->useLegacyFilenames()) {
            $pattern = '#^(?<folder>([^/]+/)*)(?<basename>((?<!__)[^/.])+)(__(?<variant>[^.]+))?(?<extension>(\..+)*)$#';
        } else {
            $pattern = '#^(?<folder>([^/]+/)*)(?<hash>[a-zA-Z0-9]{10})/(?<basename>((?<!__)[^/.])+)(__(?<variant>[^.]+))?(?<extension>(\..+)*)$#';
        }

        // not a valid file (or not a part of the filesystem)
        if (!preg_match($pattern, $fileID, $matches)) {
            return null;
        }

        $filename = $matches['folder'] . $matches['basename'] . $matches['extension'];
        $variant = isset($matches['variant']) ? $matches['variant'] : null;
        return [
            'Filename' => $filename,
            'Variant' => $variant,
            'Hash' =>  isset($matches['hash']) ? $matches['hash'] : '' 
        ];
    }
}