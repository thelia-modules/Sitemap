<?php

namespace Sitemap\Controller;

use Sitemap\Sitemap;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;

/**
 * Class SitemapConfigController
 * @package Sitemap\Controller
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapConfigController extends BaseAdminController
{
    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ["sitemap"], AccessManager::VIEW)) {
            return $response;
        }

        // Get resize mode name
        switch (Sitemap::getConfigValue('resize_mode')) {
            case 1:
                $resizeMode = 'borders';
                break;

            case 2:
                $resizeMode = 'crop';
                break;

            case 3:
                $resizeMode = 'none';
                break;

            default:
                $resizeMode = '';
                break;
        }

        // Build form
        $form = $this->createForm(
            "sitemap_config_form",
            'form',
            [
                'timeout' => Sitemap::getConfigValue('timeout'),
                'width' => Sitemap::getConfigValue('width'),
                'height' => Sitemap::getConfigValue('height'),
                'quality' => Sitemap::getConfigValue('quality'),
                'rotation' => Sitemap::getConfigValue('rotation'),
                'resize_mode' => $resizeMode,
                'background_color' => Sitemap::getConfigValue('background_color'),
                'allow_zoom' => Sitemap::getConfigValue('allow_zoom'),
                'exclude_empty_category' => Sitemap::getConfigValue('exclude_empty_category'),
                'exclude_empty_folder' => Sitemap::getConfigValue('exclude_empty_folder')
            ]
        );

        $this->getParserContext()->addForm($form);

        return $this->render("sitemap-configuration");
    }

    /**
     * Save data
     *
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function saveAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ["sitemap"], AccessManager::UPDATE)) {
            return $response;
        }

        $baseForm = $this->createForm("sitemap_config_form");

        $errorMessage = null;

        // Get current edition language locale
        $locale = $this->getCurrentEditionLocale();

        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            $excludeEmptyCategory = $data['exclude_empty_category'] == 1;
            $excludeEmptyFolder = $data['exclude_empty_folder'] == 1;

            // Get resize mode
            switch ($data["resize_mode"]) {
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

            // Save data
            Sitemap::setConfigValue('timeout', $data['timeout']);
            Sitemap::setConfigValue('width', $data['width']);
            Sitemap::setConfigValue('height', $data['height']);
            Sitemap::setConfigValue('quality', $data['quality']);
            Sitemap::setConfigValue('rotation', $data['rotation']);
            Sitemap::setConfigValue('resize_mode', $resizeMode);
            Sitemap::setConfigValue('background_color', $data['background_color']);
            Sitemap::setConfigValue('allow_zoom', $data['allow_zoom']);
            Sitemap::setConfigValue('exclude_empty_category', $excludeEmptyCategory);
            Sitemap::setConfigValue('exclude_empty_folder', $excludeEmptyFolder);

        } catch (FormValidationException $ex) {
            // Invalid data entered
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $errorMessage = $this->getTranslator()->trans('Sorry, an error occurred: %err', ['%err' => $ex->getMessage()], Sitemap::DOMAIN_NAME, $locale);
        }

        if (null !== $errorMessage) {
            // Mark the form as with error
            $baseForm->setErrorMessage($errorMessage);

            // Send the form and the error to the parser
            $this->getParserContext()
                ->addForm($baseForm)
                ->setGeneralError($errorMessage)
            ;
        } else {
            $this->getParserContext()
                ->set("success", true)
            ;
        }

        return $this->defaultAction();
    }
}