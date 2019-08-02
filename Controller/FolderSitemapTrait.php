<?php

namespace Sitemap\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Sitemap\Event\SitemapEvent;
use Sitemap\Sitemap;
use Thelia\Model\Map\ContentFolderTableMap;
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

        if (Sitemap::getConfigValue('exclude_empty_folder') == 1) {
            self::addJoinFolderCheckNotEmpty($query);
        }

        // Get folders last update
        $query->withColumn(FolderTableMap::UPDATED_AT, 'FOLDER_UPDATE_AT');

        // Execute query
        $results = $query->find();

        // For each result, hydrate XML file
        /** @var RewritingUrl $result */
        foreach ($results as $result) {
            $sitemapEvent = new SitemapEvent(
                $result,
                URL::getInstance()->absoluteUrl($result->getUrl()),
                date('c', strtotime($result->getVirtualColumn('FOLDER_UPDATE_AT')))
            );

            $this->getDispatcher()->dispatch(SitemapEvent::SITEMAP_EVENT, $sitemapEvent);

            if (!$sitemapEvent->isHide()){
                // Open new sitemap line & set brand URL & update date
                $sitemap[] = '
                <url>
                    <loc>'.$sitemapEvent->getLoc().'</loc>
                    <lastmod>'.$sitemapEvent->getLastmod().'</lastmod>
                </url>';
            }
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

    protected function addJoinFolderCheckNotEmpty(Criteria &$query)
    {
        $folderChildJoin = new Join();
        $folderChildJoin->addExplicitCondition(
            FolderTableMap::TABLE_NAME,
            'ID',
            null,
            FolderTableMap::TABLE_NAME,
            'PARENT',
            'folder_folder_child'
        );

        $folderChildJoin->setJoinType(Criteria::LEFT_JOIN);
        $query->addJoinObject($folderChildJoin, 'folderFolderChildJoin');


        $contentChildJoin = new Join();
        $contentChildJoin->addExplicitCondition(
            FolderTableMap::TABLE_NAME,
            'ID',
            null,
            ContentFolderTableMap::TABLE_NAME,
            'FOLDER_ID',
            'folder_content_child'
        );

        $contentChildJoin->setJoinType(Criteria::LEFT_JOIN);
        $query->addJoinObject($contentChildJoin, 'folderContentChildJoin');

        $query->where('(folder_folder_child.id IS NOT NULL || folder_content_child.content_id IS NOT NULL)');
        $query->addGroupByColumn('ID');
    }
}