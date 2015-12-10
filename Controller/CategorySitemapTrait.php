<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\Map\CategoryTableMap;
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

        // Get categories last update
        $query->withColumn(CategoryTableMap::UPDATED_AT, 'CATEGORY_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set category URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('CATEGORY_UPDATE_AT').'</lastmod>
            </url>';
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
}