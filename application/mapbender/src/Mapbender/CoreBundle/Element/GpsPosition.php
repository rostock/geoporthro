<?php

namespace Mapbender\CoreBundle\Element;

use Mapbender\CoreBundle\Component\Element;

/**
 *
 */
class GpsPosition extends Element
{

    /**
     * @inheritdoc
     */
    static public function getClassTitle()
    {
        return "mb.core.gpsposition.class.title";
    }

    /**
     * @inheritdoc
     */
    static public function getClassDescription()
    {
        return "mb.core.gpsposition.class.description";
    }

    /**
     * @inheritdoc
     */
    static public function getClassTags()
    {
        return array(
            "mb.core.gpsposition.tag.gpsposition",
            "mb.core.gpsposition.tag.gps",
            "mb.core.gpsposition.tag.position",
            "mb.core.gpsposition.tag.button");
    }

    /**
     * @inheritdoc
     */
    static public function listAssets()
    {
        return array(
            'js' => array(
                'mapbender.element.button.js',
                'mapbender.element.gpsPosition.js'),
            'css' => array('@MapbenderCoreBundle/Resources/public/sass/element/gpsposition.scss'),
            'trans' => array('MapbenderCoreBundle:Element:gpsposition.json.twig'));
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\CoreBundle\Element\Type\GpsPositionAdminType';
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array(
            'tooltip' => "GPS-Position",
            'label' => true,
            'autoStart' => false,
            'target' => null,
            'icon' => null,
            'refreshinterval' => '5000',
            'average' => 1,
            'follow' => false,
            'centerOnFirstPosition' => true,
            'zoomToAccuracy' => false,
            'zoomToAccuracyOnFirstPosition' => true);
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbGpsPosition';
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $configuration = $this->getConfiguration();
        return $this->container->get('templating')
                        ->render('MapbenderCoreBundle:Element:gpsposition.html.twig',
                                 array(
                            'id' => $this->getId(),
                            'configuration' => $configuration,
                            'title' => $this->getTitle()));
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderManagerBundle:Element:gpsposition.html.twig';
    }
}

