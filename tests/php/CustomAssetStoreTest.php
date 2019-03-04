<?php

namespace SilverStripe\CustomAssetsStore\Tests;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class CustomAssetsStoreTest extends SapphireTest
{
    protected static $fixture_file = 'CustomAssetsStoreTest.yml';

    public function setUp()
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
        Versioned::set_stage(Versioned::DRAFT);

        // Create a test files for each of the fixture references
        $fileIDs = array_merge(
            $this->allFixtureIDs(File::class),
            $this->allFixtureIDs(Image::class)
        );
        foreach ($fileIDs as $fileID) {
            /** @var File $file */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString($this->invertCase($file->getFilename() . ' - version 1') , $file->getFilename());
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
    }

    private function invertCase($str)
    {
        return strtolower($str) ^ strtoupper($str) ^ $str;
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testBasicSetup()
    {
        $file = File::find('FileTest-subfolder/FileTestSubfolder.txt');
        $this->assertNotNull($file);
        $this->assertEquals('fILEtEST-SUBFOLDER/fILEtESTsUBFOLDER.TXT - version 1', $file->getString());
    }
}