<?php
namespace Mapbender\CoreBundle\Element\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 *
 */
class GpsPositionAdminType extends AbstractType
{

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'gpsposition';
    }

    /**
     * @inheritdoc
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'application' => null,
            'average' => 1
        ));
    }

    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('tooltip', 'text', array('required' => false))
            ->add('label', 'checkbox', array('required' => false))
            ->add('autoStart', 'checkbox', array('required' => false))
            ->add('target', 'target_element',
                array(
                'element_class' => 'Mapbender\\CoreBundle\\Element\\Map',
                'application' => $options['application'],
                'property_path' => '[target]',
                'required' => false))
            ->add('icon', new IconClassType(), array('required' => false))
            ->add('action', 'text', array('required' => false))
            ->add('refreshinterval', 'text', array('required' => false))
            ->add('average', 'text', array(
                'required' => false,
                'property_path' => '[average]'
                ))
            ->add('follow', 'checkbox', array(
                'required' => false,
                'property_path' => '[follow]'))
            ->add('centerOnFirstPosition', 'checkbox', array(
                'required' => false,
                'property_path' => '[centerOnFirstPosition]'))
            ->add('zoomToAccuracy', 'checkbox', array(
                'required' => false,
                'property_path' => '[zoomToAccuracy]'))
            ->add('zoomToAccuracyOnFirstPosition', 'checkbox', array(
                'required' => false,
                'property_path' => '[zoomToAccuracyOnFirstPosition]'));
    }

}
