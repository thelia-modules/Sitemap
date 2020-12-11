<?php

namespace Sitemap\Hook;

use Sitemap\Model\SitemapPriority;
use Sitemap\Model\SitemapPriority as SitemapPriorityModel;
use Sitemap\Model\SitemapPriorityQuery;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Sitemap\Sitemap;

/**
 * Class SitemapHook
 * @package Sitemap\Hook
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapHook extends BaseHook
{
    private function processFieldHook(HookRenderEvent $event, $sourceType, $sourceId)
    {
        $sitemap = SitemapPriorityQuery::create()
            ->filterBySource($sourceType)
            ->filterBySourceId($sourceId)
            ->findOne();
        switch ($sourceType) {
            case 'brand':
                $sitemapConfigValue = Sitemap::getConfigValue('default_priority_brand_value', SiteMap::DEFAULT_PRIORITY_BRAND_VALUE);
                break;
            case 'category':
                $sitemapConfigValue = Sitemap::getConfigValue('default_priority_category_value', SiteMap::DEFAULT_PRIORITY_CATEGORY_VALUE);
                break;
            case 'product':
                $sitemapConfigValue = Sitemap::getConfigValue('default_priority_product_value', SiteMap::DEFAULT_PRIORITY_PRODUCT_VALUE);
                break;
            case 'folder':
            case 'content':
            default:
                $sitemapConfigValue = Sitemap::getConfigValue('default_priority_folder_value', SiteMap::DEFAULT_PRIORITY_FOLDER_VALUE);
                break;
        }
        $sitemapValue = (null === $sitemap || empty($sitemap->getValue())) ? $sitemapConfigValue : $sitemap->getValue();

        $event->add(
            $this->render(
                "generic-sitemap-definition.html",
                [
                    'sitemapPriority' => $sitemapValue
                ]
            )
        );
    }

    public function onModuleConfig(HookRenderEvent $event)
    {
        $event->add($this->render('sitemap-configuration.html'));
    }

    public function onProductEditRightColumnBottom(HookRenderEvent $event)
    {
        $this->processFieldHook($event, 'product', $event->getArgument('product_id'));
    }

    public function onCategoryEditRightColumnBottom(HookRenderEvent $event)
    {
        $this->processFieldHook($event, 'category', $event->getArgument('category_id'));
    }

    public function onContentEditRightColumnBottom(HookRenderEvent $event)
    {
        $this->processFieldHook($event, 'content', $event->getArgument('content_id'));
    }

    public function onFolderEditRightColumnBottom(HookRenderEvent $event)
    {
        $this->processFieldHook($event, 'folder', $event->getArgument('folder_id'));
    }
    public function onBrandEditRightColumnBottom(HookRenderEvent $event)
    {
        $this->processFieldHook($event, 'brand', $event->getArgument('brand_id'));
    }
}