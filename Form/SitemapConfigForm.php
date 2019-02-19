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
                ['label' => $this->translator->trans('Script timeout (in seconds) for images generation (default: 30)', [], 'sitemap')]
            )
            ->add(
                'width',
                'text',
                ['label' => $this->translator->trans('Image width', [], 'sitemap')]
            )
            ->add(
                'height',
                'text',
                ['label' => $this->translator->trans('Image height', [], 'sitemap')]
            )
            ->add(
                'quality',
                'text',
                ['label' => $this->translator->trans('Image quality', [], 'sitemap')]
            )
            ->add(
                'rotation',
                'text',
                ['label' => $this->translator->trans('Image rotation', [], 'sitemap')]
            )
            ->add(
                'resize_mode',
                'text',
                ['label' => $this->translator->trans('Image resize mode ([borders] / crop / none)', [], 'sitemap')]
            )
            ->add(
                'background_color',
                'text',
                ['label' => $this->translator->trans('Image background color', [], 'sitemap')]
            )
            ->add(
                'allow_zoom',
                'text',
                ['label' => $this->translator->trans('Allow image zoom ([false] / true)', [], 'sitemap')]
            )
            ->add(
                'exclude_empty_category',
                'text',
                ['label' => $this->translator->trans('Do not include empty categories', [], 'sitemap')]
            )
            ->add(
                'exclude_empty_folder',
                'text',
                ['label' => $this->translator->trans('Do not include empty folders', [], 'sitemap')]
            )
        ;
    }
}