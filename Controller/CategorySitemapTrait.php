<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\Map\CategoryI18nTableMap;
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

        // Get categories last update & title
        $query->withColumn(CategoryTableMap::UPDATED_AT, 'CATEGORY_UPDATE_AT');
        $query->withColumn(CategoryI18nTableMap::TITLE, 'CATEGORY_TITLE');

        // Execute query
        $results = $query->find();

        // For each result, use URL, update and image to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set category URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('CATEGORY_UPDATE_AT').'</lastmod>
            </url>';

            /* Can be used later to handle categories images

            // Handle category image
            $image = CategoryImageQuery::create()
                ->filterByCategoryId($result->getViewId())
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            if ($image !== null) {
                $this->generateSitemapImage('category', $image, $result->getVirtualColumn('CATEGORY_TITLE'), $sitemap);
            }

            // Close category line
            $sitemap[] = '            </url>';
            */
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


        // Join RewritingURL with CategoryI18n
        $joinI18n = new Join();

        $joinI18n->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            CategoryI18nTableMap::TABLE_NAME,
            'ID',
            null
        );
        $joinI18n->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_LOCALE',
            null,
            CategoryI18nTableMap::TABLE_NAME,
            'LOCALE',
            null
        );

        $joinI18n->setJoinType(Criteria::INNER_JOIN);
        $query->addJoinObject($joinI18n, 'categoryI18nJoin');
    }
}