<?php
namespace Mapbender\CoreBundle\Element;

use Mapbender\CoreBundle\Component\Element;

/**
 * Activity indicator
 *
 * @author Christian Wygoda
 */
class ActivityIndicator extends Element
{

    /**
     * @inheritdoc
     */
    static function getClassTitle()
    {
        return "mb.core.activityindicator.class.title";
    }

    /**
     * @inheritdoc
     */
    static function getClassDescription()
    {
        return "mb.core.activityindicator.class.description";
    }

    /**
     * @inheritdoc
     */
    static function getClassTags()
    {
        return array(
            "mb.core.activityindicator.tag.activity",
            "mb.core.activityindicator.tag.indicator");
    }

    /**
     * @inheritdoc
     */
    static function getDefaultConfiguration()
    {
        return array(
            'tooltip' => 'background activity',
            'activityClass' => 'mb-activity',
            'ajaxActivityClass' => 'mb-activity-ajax',
            'tileActivityClass' => 'mb-activity-tile');
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbActivityIndicator';
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\CoreBundle\Element\Type\ActivityIndicatorAdminType';
    }

    /**
     * @inheritdoc
     */
    static public function listAssets()
    {
        return array(
            'js' => array('mapbender.element.activityindicator.js'),
            'css' => array('@MapbenderCoreBundle/Resources/public/sass/element/activityindicator.scss'));
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return $this->container->get('templating')
                ->render('MapbenderCoreBundle:Element:activityindicator.html.twig',
                    array('id' => $this->getId(),
                    'title' => $this->getTitle(),
                    'configuration' => $this->getConfiguration()));
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderManagerBundle:Element:activityindicator.html.twig';
    }

}
