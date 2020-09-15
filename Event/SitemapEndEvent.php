<?php

namespace Sitemap\Event;

use Thelia\Core\Event\ActionEvent;

class SitemapEndEvent extends ActionEvent
{
   protected $sitemap;

    /**
     * @return mixed
     */
    public function getSitemap()
    {
        return $this->sitemap;
    }

    /**
     * @param mixed $sitemap
     */
    public function setSitemap($sitemap)
    {
        $this->sitemap = $sitemap;
    }
}