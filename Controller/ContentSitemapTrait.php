<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Class ContentSitemapTrait
 * @package Sitemap\Controller
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
trait ContentSitemapTrait
{
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

        // For each result, hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set content URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('CONTENT_UPDATE_AT').'</lastmod>
            </url>';
        }
    }

    /**
     * Join contents and their URLs
     *
     * @param Criteria $query
     */
    protected function addJoinContent(Criteria &$query)
    {
        // Join RewritingURL with Content to have only visible contents
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

}