<?php

namespace Sitemap\Controller;

use Doctrine\Common\Cache\FilesystemCache;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ContentI18nQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\FolderI18nQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\CategoryI18nTableMap;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Map\ContentI18nTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderI18nTableMap;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Class SitemapController
 * @package Sitemap\Controller
 * @autho Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapController extends BaseFrontController
{
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
     * Get categories
     *
     * @param $sitemap
     * @param $locale
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function setSitemapCategories(&$sitemap, $locale)
    {
        // Prepare query - get categories URL
        $query = RewritingUrlQuery::create()
            ->filterByView('category')
            ->filterByRedirected(null)
            ->filterByViewLocale($locale);

        // Join with visible categories
        self::addJoinCategory($query);

        // Get categories last update
        $query->withColumn(CategoryTableMap::UPDATED_AT, 'CATEGORY_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, use URL, update and image to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set category URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('CATEGORY_UPDATE_AT').'</lastmod>';

            // Handle category image
            $image = CategoryImageQuery::create()
                ->filterByCategoryId($result->getViewId())
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            if ($image !== null) {
                $title = CategoryI18nQuery::create()
                    ->filterById($result->getViewId())
                    ->filterByLocale($locale)
                    ->select(CategoryI18nTableMap::TITLE)
                    ->findOne();

                $this->generateSitemapImage('category', $image, $title, $sitemap);
            }

            // Close category line
            $sitemap[] = '            </url>';
        }
    }

    /**
     * Join categories and their URLs
     *
     * @param Criteria $query
     */
    protected function addJoinCategory(Criteria &$query)
    {
        // Join RewritingURL with Category
        $join = new Join();

        $join->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            CategoryTableMap::TABLE_NAME,
            'ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);

        $query->addJoinObject($join, 'categoryJoin');

        // Get only visible categories
        $query->addJoinCondition('categoryJoin', CategoryTableMap::VISIBLE, 1, Criteria::EQUAL, \PDO::PARAM_INT);
    }

    /**
     * Get products
     *
     * @param $sitemap
     * @param $locale
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function setSitemapProducts(&$sitemap, $locale)
    {
        // Prepare query - get products URL
        $query = RewritingUrlQuery::create()
            ->filterByView('product')
            ->filterByRedirected(null)
            ->filterByViewLocale($locale);

        // Join with visible products
        self::addJoinProduct($query);

        // Get products last update
        $query->withColumn(ProductTableMap::UPDATED_AT, 'PRODUCT_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, use URL and update to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set product URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('PRODUCT_UPDATE_AT').'</lastmod>';

            // Handle product image
            $image = ProductImageQuery::create()
                ->filterByProductId($result->getViewId())
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            if ($image !== null) {
                $title = ProductI18nQuery::create()
                    ->filterById($result->getViewId())
                    ->filterByLocale($locale)
                    ->select(ProductI18nTableMap::TITLE)
                    ->findOne();

                $this->generateSitemapImage('product', $image, $title, $sitemap);
            }

            // Close product line
            $sitemap[] = '            </url>';
        }
    }

    /**
     * Join products and their URLs
     *
     * @param Criteria $query
     */
    protected function addJoinProduct(Criteria &$query)
    {
        // Join RewritingURL with Product
        $join = new Join();

        $join->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            ProductTableMap::TABLE_NAME,
            'ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);

        $query->addJoinObject($join, 'productJoin');

        // Get only visible products
        $query->addJoinCondition('productJoin', ProductTableMap::VISIBLE, 1, Criteria::EQUAL, \PDO::PARAM_INT);
    }

    /**
     * Get folders
     *
     * @param $sitemap
     * @param $locale
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function setSitemapFolders(&$sitemap, $locale)
    {
        // Prepare query - get folders URL
        $query = RewritingUrlQuery::create()
            ->filterByView('folder')
            ->filterByRedirected(null)
            ->filterByViewLocale($locale);

        // Join with visible folders
        self::addJoinFolder($query);

        // Get folders last update
        $query->withColumn(FolderTableMap::UPDATED_AT, 'FOLDER_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, use URL and update to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set folder URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('FOLDER_UPDATE_AT').'</lastmod>';

            // Handle folder image
            $image = FolderImageQuery::create()
                ->filterByFolderId($result->getViewId())
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            if ($image !== null) {
                $title = FolderI18nQuery::create()
                    ->filterById($result->getViewId())
                    ->filterByLocale($locale)
                    ->select(FolderI18nTableMap::TITLE)
                    ->findOne();

                $this->generateSitemapImage('folder', $image, $title, $sitemap);
            }

            // Close folder line
            $sitemap[] = '            </url>';
        }
    }

    /**
     * Join folders and their URLs
     *
     * @param Criteria $query
     */
    protected function addJoinFolder(Criteria &$query)
    {
        // Join RewritingURL with Folder
        $join = new Join();

        $join->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            FolderTableMap::TABLE_NAME,
            'ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);

        $query->addJoinObject($join, 'folderJoin');

        // Get only visible folders
        $query->addJoinCondition('folderJoin', FolderTableMap::VISIBLE, 1, Criteria::EQUAL, \PDO::PARAM_INT);
    }

    /**
     * Get contents
     *
     * @param $sitemap
     * @param $locale
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function setSitemapContents(&$sitemap, $locale)
    {
        // Prepare query - get contents URL
        $query = RewritingUrlQuery::create()
            ->filterByView('content')
            ->filterByRedirected(null)
            ->filterByViewLocale($locale);

        // Join with visible contents
        self::addJoinContent($query);

        // Get contents last update
        $query->withColumn(ContentTableMap::UPDATED_AT, 'CONTENT_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, use URL and update to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set content URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('CONTENT_UPDATE_AT').'</lastmod>';

            // Handle content image
            $image = ContentImageQuery::create()
                ->filterByContentId($result->getViewId())
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            if ($image !== null) {
                $title = ContentI18nQuery::create()
                    ->filterById($result->getViewId())
                    ->filterByLocale($locale)
                    ->select(ContentI18nTableMap::TITLE)
                    ->findOne();

                $this->generateSitemapImage('content', $image, $title, $sitemap);
            }

            // Close folder line
            $sitemap[] = '            </url>';
        }
    }

    /**
     * Join contents and their URLs
     *
     * @param Criteria $query
     */
    protected function addJoinContent(Criteria &$query)
    {
        // Join RewritingURL with Content
        $join = new Join();

        $join->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            ContentTableMap::TABLE_NAME,
            'ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);

        $query->addJoinObject($join, 'contentJoin');

        // Get only visible products
        $query->addJoinCondition('contentJoin', ContentTableMap::VISIBLE, 1, Criteria::EQUAL, \PDO::PARAM_INT);
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
            ->setWidth(560)
            ->setHeight(408)
            ->setQuality(10)
            ->setRotation(0)
            ->setResizeMode(\Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS);

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

    /**
     * @param $locale
     * @return bool     true if the language is used, otherwise false
     */
    private function checkLang($locale)
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