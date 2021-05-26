<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Sitemap\Event\SitemapEvent;
use Sitemap\Model\SitemapPriorityQuery;
use Sitemap\Sitemap;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
    protected function setSitemapContents(&$sitemap, $locale, EventDispatcherInterface $eventDispatcher)
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
            $sitemapEvent = new SitemapEvent(
                $result,
                URL::getInstance()->absoluteUrl($result->getUrl()),
                date('c', strtotime($result->getVirtualColumn('CONTENT_UPDATE_AT')))
            );

            $eventDispatcher->dispatch($sitemapEvent, SitemapEvent::SITEMAP_EVENT);

            if (!$sitemapEvent->isHide()){
                // Open new sitemap line & set brand URL & update date

                $sitemapPriority = SitemapPriorityQuery::create()
                    ->filterBySource($result->getView())
                    ->filterBySourceId($result->getViewId())
                    ->findOne();

                $sitemapPriorityValue = ($sitemapPriority === null) ? Sitemap::getConfigValue('default_priority_folder_value', SiteMap::DEFAULT_PRIORITY_FOLDER_VALUE) : $sitemapPriority->getValue();

                $sitemap[] = '
                <url>
                    <loc>'.$sitemapEvent->getLoc().'</loc>
                    <lastmod>'.$sitemapEvent->getLastmod().'</lastmod>
                    <priority>'.$sitemapPriorityValue.'</priority>
                    <changefreq>'.Sitemap::getConfigValue('default_update_frequency', SiteMap::DEFAULT_FREQUENCY_UPDATE).'</changefreq>
                </url>';
            }
        }
    }

    /**
     * Join contents and their URLs
     *
     * @param RewritingUrlQuery $query
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function addJoinContent(RewritingUrlQuery &$query)
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
        $query->addJoinCondition('contentJoin', ContentTableMap::VISIBLE.' = 1');
    }

}