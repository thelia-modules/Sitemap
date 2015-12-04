<?php

namespace Sitemap\Controller;

use Doctrine\Common\Cache\FilesystemCache;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;

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
        $cacheContent = false;
        $cacheDir = $this->getCacheDir();
        $cacheKey = self::SITEMAP_CACHE_KEY . $locale;
        $cacheExpire = intval(ConfigQuery::read("sitemap_ttl", '7200')) ?: 7200;
        $cacheDriver = new FilesystemCache($cacheDir);

        // Check if sitemap has to be deleted
        if (!($this->checkAdmin() && "" !== $this->getRequest()->query->get("flush", ""))){
            // Get cached sitemap
            $cacheContent = $cacheDriver->fetch($cacheKey);
        } else {
            $cacheDriver->delete($cacheKey);
        }

        // If not in cache
        if (false === $cacheContent){
            // Generate sitemap
            $sitemap = $this->generateSitemap($locale);

            $cacheContent = implode("\n", $sitemap);

            // Save cache
            $cacheDriver->save($cacheKey, $cacheContent, $cacheExpire);
        }

        // Render
        $response = new Response();
        $response->setContent($cacheContent);
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

        // Get products
        $this->setSitemapProducts($sitemap, $locale);

        // End sitemap
        $sitemap[] = "\t".'</urlset>';

        return $sitemap;
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

        // Get products last update date
        $query->withColumn(ProductTableMap::UPDATED_AT, 'PRODUCT_UPDATE_AT');

        // Execute query
        $results = $query->find();


        // For each result, use URL and update date to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {
            $sitemap[] = '
            <url>
                <loc>'.$result->getUrl().'</loc>
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