<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Sitemap\Event\SitemapEvent;
use Sitemap\Model\SitemapPriorityQuery;
use Sitemap\Sitemap;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Map\BrandTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Class BrandSitemapTrait
 * @package Sitemap\Controller
 * @author Damien Foulhoux <dfoulhoux@openstudio.fr>
 */
trait BrandSitemapTrait
{
    /**
     * Get brands
     *
     * @param $sitemap
     * @param $locale
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function setSitemapBrands(&$sitemap, $locale, EventDispatcherInterface $eventDispatcher)
    {
        // Prepare query - get brands URL
        $query = RewritingUrlQuery::create()
            ->filterByView('brand')
            ->filterByRedirected(null)
            ->filterByViewLocale($locale);

        // Join with visible brands
        self::addJoinBrands($query);

        // Get brands last update
        $query->withColumn(BrandTableMap::UPDATED_AT, 'BRAND_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {
            $sitemapEvent = new SitemapEvent(
                $result,
                URL::getInstance()->absoluteUrl($result->getUrl()),
                date('c', strtotime($result->getVirtualColumn('BRAND_UPDATE_AT')))
            );

            $eventDispatcher->dispatch($sitemapEvent,SitemapEvent::SITEMAP_EVENT);

            if (!$sitemapEvent->isHide()){
                // Open new sitemap line & set brand URL & update date

                $sitemapPriority = SitemapPriorityQuery::create()
                    ->filterBySource($result->getView())
                    ->filterBySourceId($result->getViewId())
                    ->findOne();

                $sitemapPriorityValue = ($sitemapPriority === null) ? Sitemap::getConfigValue('default_priority_brand_value', SiteMap::DEFAULT_PRIORITY_BRAND_VALUE) : $sitemapPriority->getValue();

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
     * Join brands and their URLs
     *
     * @param RewritingUrlQuery $query
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function addJoinBrands(RewritingUrlQuery &$query)
    {
        // Join RewritingURL with brand to have only visible brands
        $join = new Join();

        $join->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            BrandTableMap::TABLE_NAME,
            'ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);
        $query->addJoinObject($join, 'brandJoin');

        // Get only visible products
        $query->addJoinCondition('brandJoin', BrandTableMap::VISIBLE.' = 1');
    }

}