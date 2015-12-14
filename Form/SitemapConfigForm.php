<?php

namespace Sitemap\Form;

use Thelia\Form\BaseForm;

/**
 * Class SitemapConfigForm
 * @package Sitemap\Form
 * @author Etienne Perriere <eperriere@openstudio.fr>
 */
class SitemapConfigForm extends BaseForm
{
    public function getName()
    {
        return 'sitemap_config_form';
    }

    /**
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'timeout',
                'number',
                ['label' => $this->translator->trans('Script timeout (in seconds) for images generation (default: 30)', [], 'sitemap.fo.default')]
            )
            ->add(
                'width',
                'text',
                ['label' => $this->translator->trans('Image width', [], 'sitemap.fo.default')]
            )
            ->add(
                'height',
                'text',
                ['label' => $this->translator->trans('Image height', [], 'sitemap.fo.default')]
            )
            ->add(
                'quality',
                'text',
                ['label' => $this->translator->trans('Image quality', [], 'sitemap.fo.default')]
            )
            ->add(
                'rotation',
                'text',
                ['label' => $this->translator->trans('Image rotation', [], 'sitemap.fo.default')]
            )
            ->add(
                'resize_mode',
                'text',
                ['label' => $this->translator->trans('Image resize mode ([borders] / crop / none)', [], 'sitemap.fo.default')]
            )
            ->add(
                'background_color',
                'text',
                ['label' => $this->translator->trans('Image background color', [], 'sitemap.fo.default')]
            )
            ->add(
                'allow_zoom',
                'text',
                ['label' => $this->translator->trans('Allow image zoom ([false] / true)', [], 'sitemap.fo.default')]
            )
        ;
    }
}