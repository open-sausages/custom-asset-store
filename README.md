# Silverstripe Custom Asset Store

This is an experimental module that demonstrate how you can customise your asset store to match legacy style URLs or old publish file hashes.

## To install

Add the following entry to your composer.json.
```json
{
    ...
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:open-sausages/custom-asset-store.git"
        }
    ]
}
```

Install the package with:
```bash
composer require silverstripe/custom-asset-store
```

## Why is this needed

By default, your project SilverStripe 4 will publish files under a URL containing an hash (e.g.: `/assets/Uploads/80864e1046/sam.jpg`). The hash part of the URL is based on the content of the file. If you upload a new version of the file, the hash will change which will change the URL. Direct links to old version of the files will break when this happens.

Also, if you upgraded your site from SilverStripe 3, old links to your file won't work anymore because SS3 didn't have the hash at all.

## What does this actually do

The custom asset store included in this module will try to match previously published file URL and legacy file URL to a valid published file. If it does, it will serve the content of the last published version of that file.

This will prevent broken links. Note that authenticated users bypass this logic to avoid blocking access to draft files. 

## Isn't this a bit hackish?

Yes it is. We have a [better solution in the works](https://github.com/silverstripe/silverstripe-versioned/issues/177).