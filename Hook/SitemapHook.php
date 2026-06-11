<?php

namespace Sitemap\Hook;

use Sitemap\Form\SitemapConfigForm;
use Sitemap\Model\SitemapPriorityQuery;
use Sitemap\Sitemap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

/**
 * Class SitemapHook
 * @package Sitemap\Hook
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfig'],
            ],
            'product.modification.form-right.bottom' => [
                ['type' => 'back', 'method' => 'onProductEditRightColumnBottom'],
            ],
            'category.modification.form-right.bottom' => [
                ['type' => 'back', 'method' => 'onCategoryEditRightColumnBottom'],
            ],
            'content.modification.form-right.bottom' => [
                ['type' => 'back', 'method' => 'onContentEditRightColumnBottom'],
            ],
            'folder.modification.form-right.bottom' => [
                ['type' => 'back', 'method' => 'onFolderEditRightColumnBottom'],
            ],
            'brand.modification.form-right.bottom' => [
                ['type' => 'back', 'method' => 'onBrandEditRightColumnBottom'],
            ],
        ];
    }

    private function processFieldHook(HookRenderEvent $event, string $sourceType, mixed $sourceId): void
    {
        $sitemap = SitemapPriorityQuery::create()
            ->filterBySource($sourceType)
            ->filterBySourceId($sourceId)
            ->findOne();

        switch ($sourceType) {
            case 'brand':
                $sitemapConfigValue = Sitemap::getConfigValue('default_priority_brand_value', Sitemap::DEFAULT_PRIORITY_BRAND_VALUE);
                break;
            case 'category':
                $sitemapConfigValue = Sitemap::getConfigValue('default_priority_category_value', Sitemap::DEFAULT_PRIORITY_CATEGORY_VALUE);
                break;
            case 'product':
                $sitemapConfigValue = Sitemap::getConfigValue('default_priority_product_value', Sitemap::DEFAULT_PRIORITY_PRODUCT_VALUE);
                break;
            case 'folder':
            case 'content':
            default:
                $sitemapConfigValue = Sitemap::getConfigValue('default_priority_folder_value', Sitemap::DEFAULT_PRIORITY_FOLDER_VALUE);
                break;
        }

        $sitemapValue = (null === $sitemap || empty($sitemap->getValue())) ? $sitemapConfigValue : $sitemap->getValue();

        $event->add(
            $this->render(
                'generic-sitemap-definition.html.twig',
                [
                    'sitemapPriority' => $sitemapValue,
                    'fieldName' => \sprintf('thelia_%s_modification[sitemapPriority]', $sourceType),
                ]
            )
        );
    }

    public function onModuleConfig(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(SitemapConfigForm::getName(), data: [
            'timeout'                          => Sitemap::getConfigValue('timeout'),
            'width'                            => Sitemap::getConfigValue('width'),
            'height'                           => Sitemap::getConfigValue('height'),
            'quality'                          => Sitemap::getConfigValue('quality'),
            'rotation'                         => Sitemap::getConfigValue('rotation'),
            'resize_mode'                      => Sitemap::getConfigValue('resize_mode'),
            'background_color'                 => Sitemap::getConfigValue('background_color'),
            'allow_zoom'                       => Sitemap::getConfigValue('allow_zoom'),
            'exclude_empty_category'           => Sitemap::getConfigValue('exclude_empty_category'),
            'exclude_empty_folder'             => Sitemap::getConfigValue('exclude_empty_folder'),
            'default_priority_homepage_value'  => Sitemap::getConfigValue('default_priority_homepage_value'),
            'default_priority_brand_value'     => Sitemap::getConfigValue('default_priority_brand_value'),
            'default_priority_category_value'  => Sitemap::getConfigValue('default_priority_category_value'),
            'default_priority_product_value'   => Sitemap::getConfigValue('default_priority_product_value'),
            'default_priority_folder_value'    => Sitemap::getConfigValue('default_priority_folder_value'),
            'default_update_frequency'         => Sitemap::getConfigValue('default_update_frequency'),
        ]);

        $event->add($this->render('sitemap-configuration.html.twig', ['form' => $form->createView()->getView()]));
    }

    public function onProductEditRightColumnBottom(HookRenderEvent $event): void
    {
        $this->processFieldHook($event, 'product', $event->getArgument('product_id'));
    }

    public function onCategoryEditRightColumnBottom(HookRenderEvent $event): void
    {
        $this->processFieldHook($event, 'category', $event->getArgument('category_id'));
    }

    public function onContentEditRightColumnBottom(HookRenderEvent $event): void
    {
        $this->processFieldHook($event, 'content', $event->getArgument('content_id'));
    }

    public function onFolderEditRightColumnBottom(HookRenderEvent $event): void
    {
        $this->processFieldHook($event, 'folder', $event->getArgument('folder_id'));
    }

    public function onBrandEditRightColumnBottom(HookRenderEvent $event): void
    {
        $this->processFieldHook($event, 'brand', $event->getArgument('brand_id'));
    }
}
