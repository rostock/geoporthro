<?php
namespace Mapbender\CoreBundle\Element;

use Mapbender\CoreBundle\Component\Element;

class POI extends Element
{

    /**
     * @inheritdoc
     */
    static public function getClassTitle()
    {
        return "mb.core.poi.class.title";
    }

    /**
     * @inheritdoc
     */
    static public function getClassDescription()
    {
        return "mb.core.poi.class.description";
    }

    /**
     * @inheritdoc
     */
    static public function getClassTags()
    {
        return array(
            "mb.core.poi.tag.poi",
            "mb.core.poi.tag.point",
            "mb.core.poi.tag.interest");
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\CoreBundle\Element\Type\POIAdminType';
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array(
            'useMailto' => true,
            'body' => 'Please take a look at this POI',
            'target' => null
        );
    }



    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderCoreBundle:ElementAdmin:poi.html.twig';
    }

    /**
     * @inheritdoc
     */
    static public function listAssets()
    {
        return array(
            'js' => array(
                'mapbender.element.poi.js',
                '@FOMCoreBundle/Resources/public/js/widgets/popup.js',
// to call social networks '@MapbenderCoreBundle/Resources/public/mapbender.social_media_connector.js'
            ),
            'css' => array('@MapbenderCoreBundle/Resources/public/sass/element/poi.scss'),
            'trans' => array('MapbenderCoreBundle:Element:poi.json.twig'));
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbPOI';
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return $this->container->get('templating')->render('MapbenderCoreBundle:Element:poi.html.twig',
                array(
                'id' => $this->getId(),
                'title' => $this->getTitle(),
                'configuration' => $this->getConfiguration())
        );
    }

}
