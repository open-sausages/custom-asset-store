<?php
namespace SilverStripe\CustomAssetsStore;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;

class CustomAssetStore extends FlysystemAssetStore {

    public function getResponseFor($asset)
    {
        /**
         * @var File
         */
        $file = File::get()->filter(['FileFilename' => $asset])->first();
        if ($file) {
            $asset = $file->File->getSourceURL();
            $asset = preg_replace('/^\/?assets\//i', '', $asset);
        }

        return parent::getResponseFor($asset);
    }
}