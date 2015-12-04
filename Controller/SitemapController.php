<?php

namespace Sitemap\Controller;

use Doctrine\Common\Cache\FilesystemCache;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
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
     * Build sitemap
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
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
            <url>
                <loc></loc>
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

        // For each result, use URL and update to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('CATEGORY_UPDATE_AT').'</lastmod>
            </url>';
        }
    }

    /**
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
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('PRODUCT_UPDATE_AT').'</lastmod>
            </url>';
        }
    }

    /**
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
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('FOLDER_UPDATE_AT').'</lastmod>
            </url>';
        }
    }

    /**
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
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('CONTENT_UPDATE_AT').'</lastmod>
            </url>';
        }
    }

    /**
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