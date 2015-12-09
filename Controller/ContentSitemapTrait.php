<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\Map\ContentI18nTableMap;
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
        $query->withColumn(ContentI18nTableMap::TITLE, 'CONTENT_TITLE');

        // Execute query
        $results = $query->find();

        // For each result, use URL and update to hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set content URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.$result->getVirtualColumn('CONTENT_UPDATE_AT').'</lastmod>
            </url>';

            /* Can be used later to handle contents images

            // Handle content image
            $image = ContentImageQuery::create()
                ->filterByContentId($result->getViewId())
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            if ($image !== null) {
                $this->generateSitemapImage('content', $image, $result->getVirtualColumn('CONTENT_TITLE'), $sitemap);
            }

            // Close folder line
            $sitemap[] = '            </url>';
            */
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


        // Join RewritingURL with ContentI18n
        $joinI18n = new Join();

        $joinI18n->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            ContentI18nTableMap::TABLE_NAME,
            'ID',
            null
        );
        $joinI18n->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_LOCALE',
            null,
            ContentI18nTableMap::TABLE_NAME,
            'LOCALE',
            null
        );

        $joinI18n->setJoinType(Criteria::INNER_JOIN);
        $query->addJoinObject($joinI18n);
    }

}