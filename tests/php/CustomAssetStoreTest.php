<?php

namespace SilverStripe\CustomAssetsStore\Tests;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CustomAssetsStore\CustomAssetStore;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class CustomAssetsStoreTest extends SapphireTest
{
    protected static $fixture_file = 'CustomAssetStoreTest.yml';

    public function setUp()
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
        Versioned::set_stage(Versioned::DRAFT);

        // Set backend root to /ImageTest
        TestCustomAssetStore::activate('CustomFileTest');

        // Create a test files for each of the fixture references
        $fileIDs = array_merge(
            $this->allFixtureIDs(File::class),
            $this->allFixtureIDs(Image::class)
        );
        foreach ($fileIDs as $fileID) {
            /** @var File $file */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString($this->invertCase($file->getFilename()) . ' - version 1', $file->getFilename());
            $file->write();
        }

        // Conditional fixture creation in case the 'cms' module is installed
        if (class_exists(ErrorPage::class)) {
            $page = new ErrorPage(
                array(
                    'Title' => 'Page not Found',
                    'ErrorCode' => 404
                )
            );
            $page->write();
            $page->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        }

        $this->logOut();
    }

    private function invertCase($str)
    {
        return strtolower($str) ^ strtoupper($str) ^ $str;
    }

    /**
     * @return AssetStore
     */
    private function getStore()
    {
        return Injector::inst()->get(AssetStore::class);
    }

    public function tearDown()
    {
        TestCustomAssetStore::reset();

        parent::tearDown();
    }

    public function testBasicSetup()
    {
        // We're not actually testing anything here ... just making sure the setUp works
        $file = File::find('FileTest-subfolder/FileTestSubfolder.txt');
        $this->assertNotNull($file);
        $this->assertEquals('fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 1', $file->getString());

        $assetStore = Injector::inst()->get(AssetStore::class);
        $this->assertInstanceOf(CustomAssetStore::class, $assetStore);
    }

    public function testRegularPublishedFile()
    {
        $file = File::find('FileTest-subfolder/FileTestSubfolder.txt');
        $file->publishSingle();

        $hash = substr($file->getHash(), 0, 10);

        // Normal URL with hash
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/{$hash}/FileTestSubfolder.txt");
        $this->assertEquals(200, $response->getStatusCode(), 'File should be accessible when published');
        $this->assertEquals('fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 1', $response->getBody());

        // Legacy URL
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/FileTestSubfolder.txt");
        $this->assertEquals(200, $response->getStatusCode(), 'Legacy URL should be accessible when parent file is published');
        $this->assertEquals('fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 1', $response->getBody());
    }


    public function testUnpublishedURL()
    {
        $file = File::find('FileTest-subfolder/FileTestSubfolder.txt');

        // Regular URL with hash
        $hash = substr($file->getHash(), 0, 10);
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/{$hash}/FileTestSubfolder.txt");
        $this->assertEquals(403, $response->getStatusCode(), 'Draft file should not be accessible');

        // Legacy URL
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/FileTestSubfolder.txt");
        $this->assertEquals(404, $response->getStatusCode(), 'Draft file should not be accessible via legacy URL');


        // Regular URL with hash should work when granted
        $this->getStore()->grant('FileTest-subfolder/FileTestSubfolder.txt', $file->getHash());
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/{$hash}/FileTestSubfolder.txt");
        $this->assertEquals(200, $response->getStatusCode(), 'Draft file should be accesible when granted permission');
        $this->assertEquals('fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 1', $response->getBody());

        // Legacy URL should still fail
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/FileTestSubfolder.txt");
        $this->assertEquals(
            404,
            $response->getStatusCode(),
            'Draft file should not be accessible via legacy URL, event when granted'
        );
    }

    public function testPublishedFileWithDraft()
    {
        $file = File::find('FileTest-subfolder/FileTestSubfolder.txt');
        $file->publishSingle();
        $liveHash = substr($file->getHash(), 0, 10);

        $file->setFromString('fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 2', $file->getFilename());
        $file->write();
        $draftHash = substr($file->getHash(), 0, 10);

        // Normal URL with hash
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/{$liveHash}/FileTestSubfolder.txt");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 1',
            $response->getBody(),
            'When accessing a live file that has a draft version, you should be getting the live content'
        );

        // Legacy URL
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/FileTestSubfolder.txt");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 1',
            $response->getBody(),
            'When accessing a live file that has a draft version with a legacy URL, you should be getting the live content'
        );

        // Draft URL with hash
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/{$draftHash}/FileTestSubfolder.txt");
        $this->assertEquals(
            403,
            $response->getStatusCode(),
            'You should be denied when trying to access the draft version of a live file for which you have not been granted access'
        );

        // Draft URL with hash with grant
        $this->getStore()->grant('FileTest-subfolder/FileTestSubfolder.txt', $file->getHash());
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/{$draftHash}/FileTestSubfolder.txt");
        $this->assertEquals(
            200,
            $response->getStatusCode(),
            'You should be able to access draft content when you got granted access to it'
        );
        $this->assertEquals(
            'fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 2',
            $response->getBody()
        );
    }

    public function testArchivedFile()
    {
        $file = File::find('FileTest-subfolder/FileTestSubfolder.txt');
        $file->publishSingle();
        $hash = substr($file->getHash(), 0, 10);
        $file->doUnpublish();
        $file->delete();

        // Regular URL with hash
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/{$hash}/FileTestSubfolder.txt");
        $this->assertEquals(404, $response->getStatusCode(), 'Archived file should return a 404');

        // Legacy URL
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/FileTestSubfolder.txt");
        $this->assertEquals(
            404,
            $response->getStatusCode(),
            'Archived file should not return a 404 when access via a legacy URL'
        );

        // Granted access
        $this->getStore()->grant('FileTest-subfolder/FileTestSubfolder.txt', $file->getHash());
        $response = $this->getStore()->getResponseFor("FileTest-subfolder/{$hash}/FileTestSubfolder.txt");
        $this->assertEquals(
            404,
            $response->getStatusCode(),
            'Archived file should not return a 404 even when granted access'
        );
    }
}
