<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Propel;
use Sitemap\Event\SitemapEvent;
use Sitemap\Sitemap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Trait ProductSitemapTrait.
 *
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
trait ProductSitemapTrait
{
    /**
     * Get products.
     *
     * @param $sitemap
     * @param $locale
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function setSitemapProducts(&$sitemap, $locale, EventDispatcherInterface $eventDispatcher): void
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

        // Execute query
        $results = $query->find();

        $defaultPriority = Sitemap::getConfigValue('default_priority_product_value', SiteMap::DEFAULT_PRIORITY_PRODUCT_VALUE);
        $defaultUpdateFrequency = Sitemap::getConfigValue('default_update_frequency', SiteMap::DEFAULT_FREQUENCY_UPDATE);
        $con = Propel::getConnection();

        // For each result, hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {
            $sitemapEvent = new SitemapEvent(
                $result,
                URL::getInstance()->absoluteUrl($result->getUrl()),
                date('c', strtotime($result->getVirtualColumn('PRODUCT_UPDATE_AT')))
            );

            $eventDispatcher->dispatch($sitemapEvent, SitemapEvent::SITEMAP_EVENT);

            if (!$sitemapEvent->isHide()) {
                // Open new sitemap line & set brand URL & update date

                $sql = 'SELECT value from sitemap_priority where source=:p0 and source_id=:p1';
                $stmt = $con->prepare($sql);
                $stmt->bindValue(':p0', $result->getView(), \PDO::PARAM_STR);
                $stmt->bindValue(':p1', (int) $result->getViewId(), \PDO::PARAM_INT);
                $stmt->execute();
                $sitemapPriorityValue = null;
                $sitemapPriorityValue = $stmt->fetch(\PDO::FETCH_NUM);

                if ($sitemapPriorityValue) {
                    $sitemapPriorityValue = $sitemapPriorityValue[0];
                }
                if (!$sitemapPriorityValue) {
                    $sitemapPriorityValue = $defaultPriority;
                }

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
     * Join products and their URLs.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function addJoinProduct(RewritingUrlQuery &$query): void
    {
        // Join RewritingURL with Product to have only visible products
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
        $query->addJoinCondition('productJoin', ProductTableMap::VISIBLE.' = 1');
    }
}
