<?php
namespace Mapbender\CoreBundle\Element;

use Mapbender\CoreBundle\Component\Element;

/**
 * Coordinates display
 *
 * Displays the mouse coordinates
 *
 * @author Paul Schmidt
 * @author Christian Wygoda
 */
class CoordinatesDisplay extends Element
{

    /**
     * @inheritdoc
     */
    public static function getClassTitle()
    {
        return "mb.core.coordinatesdisplay.class.title";
    }

    /**
     * @inheritdoc
     */
    public static function getClassDescription()
    {
        return "mb.core.coordinatesdisplay.class.description";
    }

    /**
     * @inheritdoc
     */
    public static function getClassTags()
    {
        return array(
            'mb.core.coordinatesdisplay.tag.coordinates',
            'mb.core.coordinatesdisplay.tag.display',
            'mb.core.coordinatesdisplay.tag.mouse',
            'mb.core.coordinatesdisplay.tag.position');
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\CoreBundle\Element\Type\CoordinatesDisplayAdminType';
    }

    /**
     * @inheritdoc
     */
    static public function listAssets()
    {
        return array(
            'js' => array('mapbender.element.coordinatesdisplay.js'),
            'css' => array('@MapbenderCoreBundle/Resources/public/sass/element/coordinatesdisplay.scss')
        );
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array(
            'tooltip' => 'coordinates display',
            'anchor' => 'right-bottom',
            'label' => false,
            'numDigits' => 2,
            'empty' => 'x= - y= -',
            'prefix' => 'x= ',
            'separator' => ' y= ',
            'target' => null
        );
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbCoordinatesDisplay';
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $a = $this->getConfiguration();
        return $this->container->get('templating')
                ->render('MapbenderCoreBundle:Element:coordinatesdisplay.html.twig',
                    array(
                    'id' => $this->getId(),
                    'title' => $this->getTitle(),
                    'configuration' => $this->getConfiguration()));
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderManagerBundle:Element:coordinatesdisplay.html.twig';
    }

}
