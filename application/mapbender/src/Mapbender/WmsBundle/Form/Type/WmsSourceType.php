<?php

namespace Mapbender\WmsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * WmsSourceType class
 */
class WmsSourceType extends AbstractType
{

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'wmssource';
    }

    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
                // Base data
                ->add('url', 'text',
                      array(
                    'attr' => array(
                        'title' => 'The application title, as shown in the browser '
                        . 'title bar and in lists.')))
                ->add('username', 'text',
                      array(
                    'attr' => array(
                        'title' => 'The slug is based on the title and used in the '
                        . 'application URL.')))
                ->add('password', 'textarea',
                      array(
                    'required' => false,
                    'attr' => array(
                        'title' => 'The description is used in overview lists.')));
    }

}

