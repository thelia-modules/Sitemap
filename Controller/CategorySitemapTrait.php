<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Sitemap\Event\SitemapEvent;
use Sitemap\Sitemap;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Trait CategorySitemapTrait
 * @package Sitemap\Controller
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
trait CategorySitemapTrait
{
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
        self::addJoinCategory($query, $locale);

        if (Sitemap::getConfigValue('exclude_empty_category') == 1) {
            self::addJoinCategoryCheckNotEmpty($query);
        }

        // Get categories last update
        $query->withColumn(CategoryTableMap::UPDATED_AT, 'CATEGORY_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {
            $sitemapEvent = new SitemapEvent(
                $result,
                URL::getInstance()->absoluteUrl($result->getUrl()),
                date('c', strtotime($result->getVirtualColumn('CATEGORY_UPDATE_AT')))
            );

            $this->getDispatcher()->dispatch(SitemapEvent::SITEMAP_EVENT, $sitemapEvent);

            if (!$sitemapEvent->isHide()) {
                // Open new sitemap line & set category URL & update date
                $sitemap[] = '
                <url>
                    <loc>' . $sitemapEvent->getLoc() . '</loc>
                    <lastmod>' . $sitemapEvent->getLastmod() . '</lastmod>
                </url>';
            }
        }
    }

    /**
     * Join categories and their URLs
     *
     * @param Criteria $query
     */
    protected function addJoinCategory(Criteria &$query)
    {
        // Join RewritingURL with Category to have only visible categories
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
     * Join categories and their URLs
     *
     * @param Criteria $query
     */
    protected function addJoinCategoryCheckNotEmpty(Criteria &$query)
    {
        $categoryChildJoin = new Join();
        $categoryChildJoin->addExplicitCondition(
            CategoryTableMap::TABLE_NAME,
            'ID',
            null,
            CategoryTableMap::TABLE_NAME,
            'PARENT',
            'category_category_child'
        );

        $categoryChildJoin->setJoinType(Criteria::LEFT_JOIN);
        $query->addJoinObject($categoryChildJoin, 'categoryCategoryChildJoin');


        $productChildJoin = new Join();
        $productChildJoin->addExplicitCondition(
            CategoryTableMap::TABLE_NAME,
            'ID',
            null,
            ProductCategoryTableMap::TABLE_NAME,
            'CATEGORY_ID',
            'category_product_child'
        );

        $productChildJoin->setJoinType(Criteria::LEFT_JOIN);
        $query->addJoinObject($productChildJoin, 'categoryProductChildJoin');

        $query->where('(category_category_child.id IS NOT NULL || category_product_child.product_id IS NOT NULL)');
        $query->addGroupByColumn('ID');
    }
}