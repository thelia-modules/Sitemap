# Sitemap

Generate sitemaps faster than Thelia default ones.

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is Sitemap.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/sitemap-module:~1.3.4
```

## Usage

Configure the module with the same information as in you product image loop.

If you have a lot of products with images, change the timeout in the configuration. **However, be aware** that it may not work depending on your server.

The sitemap will be filled with all your categories, products, folders and contents URLs, depending on the language.

The sitemap-image will be filled with all your product images (1 by product) URLs, depending on the language.

The module will be used to generate sitemap when going on http://yourSite.com/sitemap and the sitemap-image on http://yourSite.com/sitemap-image.