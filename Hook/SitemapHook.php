<?php

namespace Sitemap\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class SitemapHook
 * @package Sitemap\Hook
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapHook extends BaseHook
{
    public function onModuleConfig(HookRenderEvent $event)
    {
        $event->add($this->render('sitemap-configuration.html'));
    }
}