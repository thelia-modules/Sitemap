# Sitemap

Generate a sitemap faster than Thelia default one.

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is Sitemap.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/sitemap-module:~1.2
```

## Usage

Configure the module with the same information as in you product image loop.

The sitemap will be filled with all your categories, products, folders, contents and product images URLs.

The module will be used to generate sitemap when going on http://yourSite.com/sitemap and the sitemap image on http://yourSite.com/sitemap-image.