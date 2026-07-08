<?php

namespace Sitemap\Controller;

use Sitemap\Form\SitemapConfigForm;
use Sitemap\Sitemap;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;

/**
 * Class SitemapConfigController
 * @package Sitemap\Controller
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapConfigController extends BaseAdminController
{
    /**
     * Save the module configuration. The configuration form itself is rendered by the
     * module.configuration hook on the core /admin/module/Sitemap page (default-twig BO).
     */
    #[Route('/admin/module/Sitemap/save', name: 'sitemap_configure_save', methods: ['POST'])]
    public function saveAction(ParserContext $parserContext)
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['sitemap'], AccessManager::UPDATE)) {
            return $response;
        }

        $baseForm = $this->createForm(SitemapConfigForm::getName());

        $errorMessage = null;
        $locale = $this->getCurrentEditionLocale();

        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            $excludeEmptyCategory = $data['exclude_empty_category'] == 1;
            $excludeEmptyFolder = $data['exclude_empty_folder'] == 1;

            switch ($data['resize_mode']) {
                case 'none':
                    $resizeMode = \Thelia\Action\Image::KEEP_IMAGE_RATIO;
                    break;

                case 'crop':
                    $resizeMode = \Thelia\Action\Image::EXACT_RATIO_WITH_CROP;
                    break;

                case 'borders':
                default:
                    $resizeMode = \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS;
                    break;
            }

            Sitemap::setConfigValue('timeout', $data['timeout']);
            Sitemap::setConfigValue('width', $data['width']);
            Sitemap::setConfigValue('height', $data['height']);
            Sitemap::setConfigValue('rotation', $data['rotation']);
            Sitemap::setConfigValue('resize_mode', $resizeMode);
            Sitemap::setConfigValue('background_color', $data['background_color']);
            Sitemap::setConfigValue('allow_zoom', $data['allow_zoom']);
            Sitemap::setConfigValue('exclude_empty_category', $excludeEmptyCategory);
            Sitemap::setConfigValue('exclude_empty_folder', $excludeEmptyFolder);
            // Optional fields: an empty value falls back to the module default so the
            // generated sitemap never ends up with priority 0 / no changefreq.
            Sitemap::setConfigValue('default_priority_homepage_value', $data['default_priority_homepage_value'] ?: Sitemap::DEFAULT_PRIORITY_HOME_VALUE);
            Sitemap::setConfigValue('default_priority_brand_value', $data['default_priority_brand_value'] ?: Sitemap::DEFAULT_PRIORITY_BRAND_VALUE);
            Sitemap::setConfigValue('default_priority_category_value', $data['default_priority_category_value'] ?: Sitemap::DEFAULT_PRIORITY_CATEGORY_VALUE);
            Sitemap::setConfigValue('default_priority_product_value', $data['default_priority_product_value'] ?: Sitemap::DEFAULT_PRIORITY_PRODUCT_VALUE);
            Sitemap::setConfigValue('default_priority_folder_value', $data['default_priority_folder_value'] ?: Sitemap::DEFAULT_PRIORITY_FOLDER_VALUE);
            Sitemap::setConfigValue('default_update_frequency', $data['default_update_frequency'] ?: Sitemap::DEFAULT_FREQUENCY_UPDATE);
        } catch (FormValidationException $ex) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            $errorMessage = $this->getTranslator()->trans('Sorry, an error occurred: %err', ['%err' => $ex->getMessage()], Sitemap::DOMAIN_NAME, $locale);
        }

        if (null !== $errorMessage) {
            $baseForm->setErrorMessage($errorMessage);

            $parserContext
                ->addForm($baseForm)
                ->setGeneralError($errorMessage)
            ;
        }

        return $this->generateRedirectFromRoute('admin.module.configure', [], ['module_code' => 'Sitemap']);
    }
}
