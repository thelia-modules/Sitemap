<?php

namespace Sitemap\Controller;

use Doctrine\Common\Cache\FilesystemCache;
use Sitemap\Event\SitemapEndEvent;
use Sitemap\Event\SitemapEvent;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrl;
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
    use BrandSitemapTrait;

    use ProductImageTrait;

    /** Folder name for sitemap cache */
    const SITEMAP_CACHE_DIR = "sitemap";

    /** Key prefix for sitemap cache */
    const SITEMAP_CACHE_KEY = "sitemap";

    /** Folder name for sitemap image cache */
    const SITEMAP_IMAGE_CACHE_DIR = "sitemap-image";

    /** Key prefix for sitemap image cache */
    const SITEMAP_IMAGE_CACHE_KEY = "sitemap-image";

    protected $useFallbackTemplate = true;

    /**
     * Generate sitemap
     */
    public function generateAction()
    {
        return $this->generateSitemap(self::SITEMAP_CACHE_KEY, self::SITEMAP_CACHE_DIR);
    }

    /**
     * Generate sitemap image
     */
    public function generateImageAction()
    {
        return $this->generateSitemap(self::SITEMAP_IMAGE_CACHE_KEY, self::SITEMAP_IMAGE_CACHE_DIR);
    }

    /**
     * Check if cached sitemap can be used or generate a new one and cache it
     *
     * @param $cacheKey
     * @param $cacheDirName
     * @return Response
     */
    public function generateSitemap($cacheKey, $cacheDirName)
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
        $cacheDir = $this->getCacheDir($cacheDirName);
        $cacheKey .= $locale;
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

            // Check if we generate the standard sitemap or the sitemap image
            switch ($cacheDirName) {
                // Image
                case self::SITEMAP_IMAGE_CACHE_DIR:
                    $sitemap = $this->hydrateSitemapImage($locale);
                    break;

                // Standard
                case self::SITEMAP_CACHE_DIR:
                default:
                    $sitemap = $this->hydrateSitemap($locale);
                    break;
            }

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

    /* ------------------ */

    /**
     * Build sitemap array
     *
     * @param $locale
     * @return array
     */
    protected function hydrateSitemap($locale)
    {
        // Begin sitemap
        $sitemap = ['<?xml version="1.0" encoding="UTF-8"?>
        <!-- Generated on : '. date('Y-m-d H:i:s') .' -->
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
            <url>
                <loc>'.URL::getInstance()->getIndexPage().'</loc>
            </url>'
        ];

        // Hydrate sitemap
        $this->setSitemapCategories($sitemap, $locale);
        $this->setSitemapProducts($sitemap, $locale);
        $this->setSitemapFolders($sitemap, $locale);
        $this->setSitemapContents($sitemap, $locale);
        $this->setSitemapBrands($sitemap, $locale);

        $event = new SitemapEndEvent();
        $event->setSitemap($sitemap);

        $this->getDispatcher()->dispatch(SitemapEvent::SITEMAP_END_EVENT, $event);

        $sitemap = $event->getSitemap();

        // End sitemap
        $sitemap[] = "\t".'</urlset>';

        return $sitemap;
    }

    /**
     * Build sitemap image array
     *
     * @param $locale
     * @return array
     */
    protected function hydrateSitemapImage($locale)
    {
        // Begin sitemap image
        $sitemap = ['<?xml version="1.0" encoding="UTF-8"?>
        <!-- Generated on : '. date('Y-m-d H:i:s') .' -->
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
            <url>
                <loc>'.URL::getInstance()->getIndexPage().'</loc>
            </url>'
        ];

        // Hydrate sitemap image
        $this->setSitemapProductImages($sitemap, $locale);

        // End sitemap image
        $sitemap[] = "\t".'</urlset>';

        return $sitemap;
    }

    /* ------------------ */

    /**
     * @param $type
     * @param RewritingUrl $result
     * @param $configValues
     * @param $sitemap
     */
    protected function generateSitemapImage($type, $result, $configValues, &$sitemap)
    {
        $event = new ImageEvent();

        $event
            ->setWidth($configValues['width'])
            ->setHeight($configValues['height'])
            ->setQuality($configValues['quality'])
            ->setRotation($configValues['rotation'])
            ->setResizeMode($configValues['resizeMode'])
            ->setBackgroundColor($configValues['bgColor'])
            ->setAllowZoom($configValues['allowZoom']);

        // Put source image file path
        $source_filepath = sprintf("%s%s/%s/%s",
            THELIA_ROOT,
            ConfigQuery::read('images_library_path', 'local/media/images'),
            $type,
            $result->getVirtualColumn('PRODUCT_FILE')
        );

        $event->setSourceFilepath($source_filepath);
        $event->setCacheSubdirectory($type);

        try {
            // Dispatch image processing event
            $this->dispatch(TheliaEvents::IMAGE_PROCESS, $event);

            // New sitemap image entry
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <image:image>
                    <image:loc>'.$event->getFileUrl().'</image:loc>
                    <image:title>'.htmlspecialchars($result->getVirtualColumn('PRODUCT_TITLE')).'</image:title>
                </image:image>
            </url>';

        } catch (\Exception $ex) {
        }
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
     * @param $cacheDirName
     * @return mixed|string
     */
    protected function getCacheDir($cacheDirName)
    {
        $cacheDir = $this->container->getParameter("kernel.cache_dir");
        $cacheDir = rtrim($cacheDir, '/');
        $cacheDir .= '/' . $cacheDirName . '/';

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