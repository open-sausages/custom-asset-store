<?php

namespace SilverStripe\CustomAssetsStore\Tests;

use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use SilverStripe\Assets\Filesystem as SSFilesystem;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;
use SilverStripe\Assets\Flysystem\ProtectedAssetAdapter;
use SilverStripe\Assets\Flysystem\PublicAssetAdapter;
use SilverStripe\Assets\Storage\AssetContainer;
use SilverStripe\Assets\Flysystem\GeneratedAssets;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\AssetStoreRouter;
use SilverStripe\Assets\Storage\DBFile;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Storage\GeneratedAssetHandler;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CustomAssetsStore\CustomAssetStore;
use SilverStripe\Dev\TestOnly;
use SilverStripe\View\Requirements;

/**
 * Allows you to mock a backend store in a custom directory beneath assets.
 * Only to be used for mocking test fixtures
 */
class TestCustomAssetStore extends CustomAssetStore implements TestOnly
{
    /**
     * Enable disclosure of secure assets. The real Asset store will return a 404.
     *
     * @config
     * @var    int
     */
    private static $denied_response_code = 403;

    /**
     * Set to true|false to override all isSeekableStream calls
     *
     * @var null|bool
     */
    public static $seekable_override = null;

    /**
     * Base dir of current file
     *
     * @var string
     */
    public static $basedir = null;

    /**
     * Set this store as the new asset backend
     *
     * @param string $basedir Basedir to store assets, which will be placed beneath 'assets' folder
     */
    public static function activate($basedir)
    {
        // Assign this as the new store
        $publicAdapter = new PublicAssetAdapter(ASSETS_PATH . '/' . $basedir);
        $publicFilesystem = new Filesystem(
            $publicAdapter,
            [
                'visibility' => AdapterInterface::VISIBILITY_PUBLIC
            ]
        );
        $protectedAdapter = new ProtectedAssetAdapter(ASSETS_PATH . '/' . $basedir . '/.protected');
        $protectedFilesystem = new Filesystem(
            $protectedAdapter,
            [
                'visibility' => AdapterInterface::VISIBILITY_PRIVATE
            ]
        );

        $backend = new self();
        $backend->setPublicFilesystem($publicFilesystem);
        $backend->setProtectedFilesystem($protectedFilesystem);
        Injector::inst()->registerService($backend, AssetStore::class);
        Injector::inst()->registerService($backend, AssetStoreRouter::class);

        // Assign flysystem backend to generated asset handler at the same time
        $generated = new GeneratedAssets();
        $generated->setFilesystem($publicFilesystem);
        Injector::inst()->registerService($generated, GeneratedAssetHandler::class);
        Requirements::backend()->setAssetHandler($generated);

        // Disable legacy and set defaults
        FlysystemAssetStore::config()->set('legacy_filenames', false);
        Director::config()->set('alternate_base_url', '/');
        DBFile::config()->set('force_resample', false);
        File::config()->set('force_resample', false);
        self::reset();
        self::$basedir = $basedir;

        // Ensure basedir exists
        SSFilesystem::makeFolder(self::base_path());
    }

    /**
     * Get absolute path to basedir
     *
     * @return string
     */
    public static function base_path()
    {
        if (!self::$basedir) {
            return null;
        }
        return ASSETS_PATH . '/' . self::$basedir;
    }

    /**
     * Reset defaults for this store
     */
    public static function reset()
    {
        // Remove all files in this store
        if (self::$basedir) {
            $path = self::base_path();
            if (file_exists($path)) {
                SSFilesystem::removeFolder($path);
            }
        }
        self::$seekable_override = null;
        self::$basedir = null;
    }

    /**
     * Helper method to get local filesystem path for this file
     *
     * @param  AssetContainer $asset
     * @return string
     */
    public static function getLocalPath(AssetContainer $asset)
    {
        if ($asset instanceof Folder) {
            return self::base_path() . '/' . $asset->getFilename();
        }
        if ($asset instanceof File) {
            $asset = $asset->File;
        }
        // Extract filesystem used to store this object
        /** @var TestAssetStore $assetStore */
        $assetStore = Injector::inst()->get(AssetStore::class);
        $fileID = $assetStore->getFileID($asset->Filename, $asset->Hash, $asset->Variant);
        /** @var Filesystem $filesystem */
        $filesystem = $assetStore->getProtectedFilesystem();
        if (!$filesystem->has($fileID)) {
            $filesystem = $assetStore->getPublicFilesystem();
        }
        /** @var Local $adapter */
        $adapter = $filesystem->getAdapter();
        return $adapter->applyPathPrefix($fileID);
    }

}
