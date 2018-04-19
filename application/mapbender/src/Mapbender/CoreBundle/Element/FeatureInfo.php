<?php

namespace Mapbender\CoreBundle\Element;

use Mapbender\CoreBundle\Component\Element;

/**
 * Featureinfo element
 *
 * This element will provide feature info for most layer types
 *
 * @author Christian Wygoda
 */
class FeatureInfo extends Element
{

    /**
     * @inheritdoc
     */
    public static function getClassTitle()
    {
        return "mb.core.featureinfo.class.title";
    }

    /**
     * @inheritdoc
     */
    public static function getClassDescription()
    {
        return "mb.core.featureinfo.class.description";
    }

    /**
     * @inheritdoc
     */
    public static function getClassTags()
    {
        return array(
            "mb.core.featureinfo.tag.feature",
            "mb.core.featureinfo.tag.featureinfo",
            "mb.core.featureinfo.tag.info",
            "mb.core.featureinfo.tag.dialog");
    }

    /**
     * @inheritdoc
     */
    public function getConfiguration()
    {
        $config = parent::getConfiguration();
        if (!isset($config['width']) || !$config['width']) {
            $default = self::getDefaultConfiguration();
            $config['width'] = $default['width'];
        }

        if (!isset($config['height']) || !$config['height']) {
            $default = $default ? $default : self::getDefaultConfiguration();
            $config['height'] = $default['height'];
        }
        return $config;
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array(
            'tooltip' => 'Feature Info Dialog',
            'type' => 'dialog',
            "autoActivate" => false,
            "deactivateOnClose" => true,
            "printResult" => false,
            "showOriginal" => false,
            "onlyValid" => false,
            "displayType" => 'tabs',
            "target" => null,
            "width" => 700,
            "height" => 500
        );
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbFeatureInfo';
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\CoreBundle\Element\Type\FeatureInfoAdminType';
    }

    /**
     * @inheritdoc
     */
    public static function listAssets()
    {
        return array(
            'js' => array(
                'mapbender.element.featureInfo.js',
                '@FOMCoreBundle/Resources/public/js/frontend/tabcontainer.js',
                '@FOMCoreBundle/Resources/public/js/widgets/popup.js'
            ),
            'css' => array(
                '@MapbenderCoreBundle/Resources/public/sass/element/featureinfo.scss'
            ),
            'trans' => array('MapbenderCoreBundle:Element:featureinfo.json.twig')
        );
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $configuration = parent::getConfiguration();
        return $this->container->get('templating')
                ->render(
                    'MapbenderCoreBundle:Element:featureinfo.html.twig',
                    array(
                    'id' => $this->getId(),
                    'configuration' => $configuration,
                    'title' => $this->getTitle())
        );
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderCoreBundle:ElementAdmin:featureinfo.html.twig';
    }
}
