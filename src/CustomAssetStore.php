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

        // We skipped this part if you have been granted access to the file
        if ($parsedFileID && isset($parsedFileID['Hash'])) {
            if (!$this->isGranted($asset)) {
                $asset = $this->rewriteToLatestLiveUrl($parsedFileID) ?: $asset;
            }
        } else {
            $asset = $this->rewriteLegacyUrl($asset) ?: $asset;
        }

        return parent::getResponseFor($asset);
    }

    /**
     * Given a parsed file id, try to find the latest live url for this file.
     * @param $parsedFileID
     * @return false|string
     */
    protected function rewriteToLatestLiveUrl($parsedFileID)
    {
        $file = Versioned::withVersionedMode(function() use ($parsedFileID) {
            Versioned::set_stage(Versioned::LIVE);
            return File::get()->filter(['FileFilename' => $parsedFileID['Filename']])->first();
        });

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
                return $this->getFileID($file->getFilename(), $file->getHash(), $parsedFileID['Variant']);
            }
        }

        return false;
    }

    /**
     * Try to map the file path as it would appear in the CMS to an actual file.
     * @param $asset
     * @return bool|string
     */
    protected function rewriteLegacyUrl($asset)
    {
        // Let's try to match the plain file name
        $file = Versioned::withVersionedMode(function() use ($asset) {
            Versioned::set_stage(Versioned::LIVE);
            return File::get()->filter(['FileFilename' => $asset])->first();
        });

        return $file ?
            $this->getFileID($file->getFilename(), $file->getHash()) :
            false;
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