<?php

namespace Sitemap\Controller;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Trait ProductSitemapTrait
 * @package Sitemap\Controller
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
trait ProductSitemapTrait
{
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
        $query->withColumn(ProductI18nTableMap::TITLE, 'PRODUCT_TITLE');

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
                $this->generateSitemapImage('product', $image, $result->getVirtualColumn('PRODUCT_TITLE'), $sitemap);
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


        // Join RewritingURL with ProductI18n
        $joinI18n = new Join();

        $joinI18n->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            ProductI18nTableMap::TABLE_NAME,
            'ID',
            null
        );
        $joinI18n->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_LOCALE',
            null,
            ProductI18nTableMap::TABLE_NAME,
            'LOCALE',
            null
        );

        $joinI18n->setJoinType(Criteria::INNER_JOIN);
        $query->addJoinObject($joinI18n);
    }
}