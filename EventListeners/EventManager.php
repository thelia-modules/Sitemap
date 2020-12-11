<?php
/*************************************************************************************/
/*                                                                                   */
/*      Copyright (c) Franck Allimant, CQFDev                                        */
/*      email : thelia@cqfdev.fr                                                     */
/*      web : http://www.cqfdev.fr                                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*                                                                                   */
/*************************************************************************************/

namespace Sitemap\EventListeners;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Sitemap\Model\Base\SitemapPriority;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sitemap\Model\Map\SitemapPriorityTableMap;
use Sitemap\Model\SitemapPriorityQuery;
use Sitemap\Sitemap;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Brand\BrandEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentEvent;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\Folder\FolderDeleteEvent;
use Thelia\Core\Event\Folder\FolderEvent;
use Thelia\Core\Event\Loop\LoopExtendsArgDefinitionsEvent;
use Thelia\Core\Event\Loop\LoopExtendsBuildModelCriteriaEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Map\BrandDocumentTableMap;
use Thelia\Model\Map\BrandImageTableMap;
use Thelia\Model\Map\BrandTableMap;
use Thelia\Model\Map\CategoryDocumentTableMap;
use Thelia\Model\Map\CategoryImageTableMap;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Map\ContentDocumentTableMap;
use Thelia\Model\Map\ContentImageTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderDocumentTableMap;
use Thelia\Model\Map\FolderImageTableMap;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\ProductDocumentTableMap;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Model\Map\ProductSaleElementsProductImageTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Tools\URL;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

class EventManager implements EventSubscriberInterface
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::PRODUCT_DELETE  => [ 'deleteProduct' ],
            TheliaEvents::CATEGORY_DELETE => [ 'deleteCategory' ],
            TheliaEvents::CONTENT_DELETE  => [ 'deleteContent' ],
            TheliaEvents::FOLDER_DELETE   => [ 'deleteFolder' ],
            TheliaEvents::BRAND_DELETE    => [ 'deleteBrand' ],

            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_product_creation" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_product_modification" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_content_creation" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_content_modification" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_category_creation" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_category_modification" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_folder_creation" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_folder_modification" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_brand_creation" => ['addFieldToForm', 128],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_brand_modification" => ['addFieldToForm', 128],

            TheliaEvents::PRODUCT_UPDATE  => ['processProductFields', 100],
            TheliaEvents::PRODUCT_CREATE  => ['processProductFields', 100],

            TheliaEvents::CATEGORY_CREATE  => ['processCategoryFields', 100],
            TheliaEvents::CATEGORY_UPDATE  => ['processCategoryFields', 100],

            TheliaEvents::CONTENT_CREATE  => ['processContentFields', 100],
            TheliaEvents::CONTENT_UPDATE  => ['processContentFields', 100],

            TheliaEvents::FOLDER_CREATE  => ['processFolderFields', 100],
            TheliaEvents::FOLDER_UPDATE  => ['processFolderFields', 100],

            TheliaEvents::BRAND_CREATE  => ['processBrandFields', 100],
            TheliaEvents::BRAND_UPDATE  => ['processBrandFields', 100],
        ];
    }

    public function addFieldToForm(TheliaFormEvent $event)
    {
        $event->getForm()->getFormBuilder()->add(
            'sitemapPriority',
            'text',
            [
                'required' => false,
                'label' => Translator::getInstance()->trans(
                    'Sitemap priority',
                    [],
                    Sitemap::DOMAIN_NAME
                ),
                'label_attr'  => [
                    'help' => Translator::getInstance()->trans(
                        'Enter a decimal number between 0 and 1 that will define the importance of the page.',
                        [],
                        Sitemap::DOMAIN_NAME
                    )
                ]
            ]
        );
    }

    public function processSitemap(ActionEvent $event, $source, $sourceId)
    {
        // Utilise le principe NON DOCUMENTE qui dit que si une form bindée à un event trouve
        // un champ absent de l'event, elle le rend accessible à travers une méthode magique.
        // (cf. ActionEvent::bindForm())

        $sitemapPriority = SitemapPriorityQuery::create()
            ->filterBySource($source)
            ->filterBySourceId($sourceId)
            ->findOne();

        $sitemapValue = $event->sitemapPriority;

        if (!empty($sitemapValue) && $sitemapPriority->getValue() !== $sitemapValue) {
            $sitemapPriority
                ->setValue($sitemapValue)->save();
        }
    }

    public function processProductFields(ProductEvent $event)
    {
        if ($event->hasProduct()) {
            $this->processSitemap($event, 'product', $event->getProduct()->getId());
        }
    }

    public function processCategoryFields(CategoryEvent $event)
    {
        if ($event->hasCategory()) {
            $this->processSitemap($event, 'category', $event->getCategory()->getId());
        }
    }

    public function processFolderFields(FolderEvent $event)
    {
        if ($event->hasFolder()) {
            $this->processSitemap($event, 'folder', $event->getFolder()->getId());
        }
    }

    public function processContentFields(ContentEvent $event)
    {
        if ($event->hasContent()) {
            $this->processSitemap($event, 'content', $event->getContent()->getId());
        }
    }

    public function processBrandFields(BrandEvent $event)
    {
        if ($event->hasBrand()) {
            $this->processSitemap($event, 'brand', $event->getBrand()->getId());
        }
    }

    public function deleteProduct(ProductDeleteEvent $event)
    {
        SitemapPriorityQuery::create()->filterBySource('product')->filterBySourceId($event->getProductId())->delete();
    }

    public function deleteCategory(CategoryDeleteEvent $event)
    {
        SitemapPriorityQuery::create()->filterBySource('category')->filterBySourceId($event->getCategoryId())->delete();
    }

    public function deleteContent(ContentDeleteEvent $event)
    {
        SitemapPriorityQuery::create()->filterBySource('content')->filterBySourceId($event->getContentId())->delete();
    }

    public function deleteFolder(FolderDeleteEvent $event)
    {
        SitemapPriorityQuery::create()->filterBySource('folder')->filterBySourceId($event->getFolderId())->delete();
    }

    public function deleteBrand(BrandDeleteEvent $event)
    {
        SitemapPriorityQuery::create()->filterBySource('brand')->filterBySourceId($event->getBrandId())->delete();
    }
}
