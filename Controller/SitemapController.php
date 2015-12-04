<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;

/**
 * Class SitemapController
 * @package Sitemap\Controller
 * @autho Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapController extends BaseAdminController
{
    protected $useFallbackTemplate = true;

    public function generateAction()
    {
        // Begin sitemap
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>
            <!-- Generated on : '. date('Y-m-d H:i:s') .' -->
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
            xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        // Get products
        $sitemap = $this->setSitemapProducts($sitemap);

        // End sitemap
        $sitemap .= '</urlset>';

        // Render
        $response = new Response();
        $response->setContent($sitemap);
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }

    protected function setSitemapProducts($sitemap)
    {
        // Prepare query - get products URL
        $query = RewritingUrlQuery::create()
            ->filterByView('product')
            ->filterByRedirected(null);

        // Join with visible products
        self::addJoinProduct($query);

        // Get products last update date
        $query->withColumn(ProductTableMap::UPDATED_AT, 'PRODUCT_UPDATE_AT');

        // Execute query
        $results = $query->find();


        // For each result, use URL and update date to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {
            $sitemap .= '<url> <loc>'.$result->getUrl().'</loc> <lastmod>'.$result->getVirtualColumn('PRODUCT_UPDATE_AT').'</lastmod> </url>';
        }

        return $sitemap;
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
}