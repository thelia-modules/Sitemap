<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 01/08/2019
 * Time: 10:29
 */

namespace Sitemap\Event;


use RewriteUrl\RewriteUrl;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\RewritingUrl;

class SitemapEvent extends ActionEvent
{
    const SITEMAP_EVENT = 'sitemap_event';

    /** @var RewritingUrl */
    protected $rewritingUrl;

    /** @var string */
    protected $loc;

    /** @var string */
    protected $lastmod;

    /** @var boolean */
    protected $hide;

    public function __construct(RewritingUrl $rewritingUrl = null, $loc = "", $lastmod = "", $hide = false)
    {
        $this->rewritingUrl = $rewritingUrl;

        $this->loc = $loc;

        $this->lastmod = $lastmod;

        $this->hide = $hide;

    }

    /**
     * @return RewritingUrl
     */
    public function getRewritingUrl()
    {
        return $this->rewritingUrl;
    }

    /**
     * @param RewritingUrl $rewritingUrl
     */
    public function setRewritingUrl($rewritingUrl)
    {
        $this->rewritingUrl = $rewritingUrl;
    }


    /**
     * @return null
     */
    public function getLoc()
    {
        return $this->loc;
    }

    /**
     * @param null $loc
     */
    public function setLoc($loc)
    {
        $this->loc = $loc;
    }

    /**
     * @return null
     */
    public function getLastmod()
    {
        return $this->lastmod;
    }

    /**
     * @param null $lastmod
     */
    public function setLastmod($lastmod)
    {
        $this->lastmod = $lastmod;
    }

    /**
     * @return bool
     */
    public function isHide()
    {
        return $this->hide;
    }

    /**
     * @param bool $hide
     */
    public function setHide($hide)
    {
        $this->hide = $hide;
    }


}