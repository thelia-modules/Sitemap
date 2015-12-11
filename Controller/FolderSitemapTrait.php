<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Class FolderSitemapTrait
 * @package Sitemap\Controller
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
trait FolderSitemapTrait
{
    /**
     * Get folders
     *
     * @param $sitemap
     * @param $locale
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function setSitemapFolders(&$sitemap, $locale)
    {
        // Prepare query - get folders URL
        $query = RewritingUrlQuery::create()
            ->filterByView('folder')
            ->filterByRedirected(null)
            ->filterByViewLocale($locale);

        // Join with visible folders
        self::addJoinFolder($query);

        // Get folders last update
        $query->withColumn(FolderTableMap::UPDATED_AT, 'FOLDER_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {

            // Open new sitemap line & set folder URL & update date
            $sitemap[] = '
            <url>
                <loc>'.URL::getInstance()->absoluteUrl($result->getUrl()).'</loc>
                <lastmod>'.date('c', strtotime($result->getVirtualColumn('FOLDER_UPDATE_AT'))).'</lastmod>
            </url>';
        }
    }

    /**
     * Join folders and their URLs
     *
     * @param Criteria $query
     */
    protected function addJoinFolder(Criteria &$query)
    {
        // Join RewritingURL with Folder to have only visible folders
        $join = new Join();

        $join->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'VIEW_ID',
            null,
            FolderTableMap::TABLE_NAME,
            'ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);
        $query->addJoinObject($join, 'folderJoin');

        // Get only visible folders
        $query->addJoinCondition('folderJoin', FolderTableMap::VISIBLE, 1, Criteria::EQUAL, \PDO::PARAM_INT);
    }
}