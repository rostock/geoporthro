<?php

namespace Mapbender\CoreBundle\Element\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class LayerThemeType extends AbstractType
{

    public function getName()
    {
        return 'theme';
    }

    /**
     * @inheritdoc
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'id' => null,
            'title' => '',
            'useTheme' => true,
            'opened' => true,
            'sourceVisibility' => false,
            'allSelected' => false,
        ));
    }

    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('id', 'hidden', array('required' => true, 'property_path' => '[id]'))
            ->add('title', 'hidden', array('required' => false, 'property_path' => '[title]'))
            ->add('useTheme', 'checkbox', array('required' => false, 'property_path' => '[useTheme]'))
            ->add('opened', 'checkbox', array('required' => false, 'property_path' => '[opened]'))
            ->add('sourceVisibility', 'checkbox', array('required' => false, 'property_path' => '[sourceVisibility]'))
            ->add('allSelected', 'checkbox', array('required' => false, 'property_path' => '[allSelected]'))
            ->add('allUnSelected', 'checkbox', array('required' => false, 'property_path' => '[allUnSelected]'));
    }

}
