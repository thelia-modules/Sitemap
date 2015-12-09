<?php

namespace Sitemap\Controller;

use Doctrine\Common\Cache\FilesystemCache;
use Sitemap\Sitemap;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * Class SitemapController
 * @package Sitemap\Controller
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapController extends BaseFrontController
{
    use CategorySitemapTrait;
    use ProductSitemapTrait;
    use FolderSitemapTrait;
    use ContentSitemapTrait;

    /** Folder name for sitemap cache */
    const SITEMAP_CACHE_DIR = "sitemap";

    /** Key prefix for sitemap cache */
    const SITEMAP_CACHE_KEY = "sitemap";

    protected $useFallbackTemplate = true;

    /**
     * Check if cache sitemap can be used or generate a new one and cache it
     *
     * @return Response
     */
    public function generateAction()
    {
        // Get and check locale
        $locale = $this->getSession()->getLang()->getLocale();

        if ("" !== $locale) {
            if (! $this->checkLang($locale)){
                $this->pageNotFound();
            }
        }

        // Get sitemap cache information
        $sitemapContent = false;
        $cacheDir = $this->getCacheDir();
        $cacheKey = self::SITEMAP_CACHE_KEY . $locale;
        $cacheExpire = intval(ConfigQuery::read("sitemap_ttl", '7200')) ?: 7200;
        $cacheDriver = new FilesystemCache($cacheDir);

        // Check if sitemap has to be deleted
        if (!($this->checkAdmin() && "" !== $this->getRequest()->query->get("flush", ""))){
            // Get cached sitemap
            $sitemapContent = $cacheDriver->fetch($cacheKey);
        } else {
            $cacheDriver->delete($cacheKey);
        }

        // If not in cache, generate and cache it
        if (false === $sitemapContent){
            // Generate sitemap function
            $sitemap = $this->generateSitemap($locale);

            $sitemapContent = implode("\n", $sitemap);

            // Save cache
            $cacheDriver->save($cacheKey, $sitemapContent, $cacheExpire);
        }

        // Render
        $response = new Response();
        $response->setContent($sitemapContent);
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }

    /**
     * Build sitemap array
     *
     * @param $locale
     * @return array
     */
    protected function generateSitemap($locale)
    {
        // Begin sitemap
        $sitemap = ['<?xml version="1.0" encoding="UTF-8"?>
        <!-- Generated on : '. date('Y-m-d H:i:s') .' -->
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
            <url>
                <loc>'.URL::getInstance()->getIndexPage().'</loc>
            </url>'
        ];

        // Hydrate sitemap
        $this->setSitemapCategories($sitemap, $locale);
        $this->setSitemapProducts($sitemap, $locale);
        $this->setSitemapFolders($sitemap, $locale);
        $this->setSitemapContents($sitemap, $locale);

        // End sitemap
        $sitemap[] = "\t".'</urlset>';

        return $sitemap;
    }

    /* ------------------ */

    /**
     * @param $type
     * @param $image
     * @param $title
     * @param $sitemap
     */
    protected function generateSitemapImage($type, $image, $title, &$sitemap)
    {
        $event = new ImageEvent();

        $event
            ->setWidth(Sitemap::getConfigValue('width', 560))
            ->setHeight(Sitemap::getConfigValue('height', 445))
            ->setQuality(Sitemap::getConfigValue('quality', 75))
            ->setRotation(Sitemap::getConfigValue('rotation', 0))
            ->setResizeMode(Sitemap::getConfigValue('resize_mode', \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS))
            ->setBackgroundColor(Sitemap::getConfigValue('background_color'))
            ->setAllowZoom(Sitemap::getConfigValue('allow_zoom', false));

        // Put source image file path
        $source_filepath = sprintf("%s%s/%s/%s",
            THELIA_ROOT,
            ConfigQuery::read('images_library_path', 'local/media/images'),
            $type,
            $image->getFile()
        );

        $event->setSourceFilepath($source_filepath);
        $event->setCacheSubdirectory($type);

        try {
            // Dispatch image processing event
            $this->dispatch(TheliaEvents::IMAGE_PROCESS, $event);
        } catch (\Exception $ex) {
        }

        // Set image path in the sitemap file
        $sitemap[] = '
                <image:image>
                    <image:loc>'.$event->getFileUrl().'</image:loc>
                    <image:title>'.$title.'</image:title>
                </image:image>';
    }

    /* ------------------ */

    /**
     * @param $locale
     * @return bool     true if the language is used, otherwise false
     */
    protected function checkLang($locale)
    {
        // Load locales
        $locale = LangQuery::create()
            ->findOneByLocale($locale);

        return (null !== $locale);
    }

    /**
     * Get the cache directory for sitemap
     *
     * @return mixed|string
     */
    protected function getCacheDir()
    {
        $cacheDir = $this->container->getParameter("kernel.cache_dir");
        $cacheDir = rtrim($cacheDir, '/');
        $cacheDir .= '/' . self::SITEMAP_CACHE_DIR . '/';

        return $cacheDir;
    }

    /**
     * Check if current user has ADMIN role
     *
     * @return bool
     */
    protected function checkAdmin(){
        return $this->getSecurityContext()->hasAdminUser();
    }
}