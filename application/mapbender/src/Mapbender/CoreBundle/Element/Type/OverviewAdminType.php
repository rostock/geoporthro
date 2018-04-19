<?php

namespace Mapbender\CoreBundle\Element\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * 
 */
class OverviewAdminType extends AbstractType
{

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'overview';
    }

    /**
     * @inheritdoc
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'application' => null
        ));
    }

    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $app = $options['application'];
        $builder
            ->add('tooltip', 'text', array('required' => false))
            ->add('layerset', 'app_layerset',
                  array(
                'application'   => $options['application'],
                'property_path' => '[layerset]',
                'required'      => true))
            ->add('target', 'target_element',
                  array(
                'element_class' => 'Mapbender\\CoreBundle\\Element\\Map',
                'application'   => $options['application'],
                'property_path' => '[target]',
                'required'      => false))
            ->add('anchor', "choice",
                  array(
                'required' => true,
                "choices"  => array(
                    'left-top'     => 'left-top',
                    'left-bottom'  => 'left-bottom',
                    'right-top'    => 'right-top',
                    'right-bottom' => 'right-bottom')))
            ->add('maximized', 'checkbox', array('required' => false))
            ->add('fixed', 'checkbox', array('required' => false))
            ->add('width', 'text', array('required' => true))
            ->add('height', 'text', array('required' => true));
    }

}