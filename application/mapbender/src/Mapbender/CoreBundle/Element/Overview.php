<?php
namespace Mapbender\CoreBundle\Element;

use Mapbender\CoreBundle\Component\Element;
use Mapbender\ManagerBundle\Component\Mapper;

/**
 * Map's overview element
 *
 * @author Paul Schmidt
 */
class Overview extends Element
{

    /**
     * @inheritdoc
     */
    public static function getClassTitle()
    {
        return "mb.core.overview.class.title";
    }

    /**
     * @inheritdoc
     */
    public static function getClassDescription()
    {
        return "mb.core.overview.class.description";
    }

    /**
     * @inheritdoc
     */
    public static function getClassTags()
    {
        return array(
            "mb.core.overview.tag.overview",
            "mb.core.overview.tag.map");
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array(
            'title' => 'Overview',
            'tooltip' => "Overview",
            'layerset' => null,
            'target' => null,
            'width' => 200,
            'height' => 100,
            'anchor' => 'right-top',
            'position' => array('0px', '0px'),
            'maximized' => true,
            'fixed' => true);
    }

    /**
     * @inheritdoc
     */
    public function getConfiguration()
    {
        $configuration = parent::getConfiguration();
        $configuration['target'] = strval($configuration['target']);
        return $configuration;
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbOverview';
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\CoreBundle\Element\Type\OverviewAdminType';
    }

    /**
     * @inheritdoc
     */
    public static function listAssets()
    {
        return array(
            'js' => array('mapbender.element.overview.js'),
            //TODO: Split up
            'css' => array('@MapbenderCoreBundle/Resources/public/sass/element/overview.scss'));
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return $this->container->get('templating')->render(
            'MapbenderCoreBundle:Element:overview.html.twig',
            array(
                'id' => $this->getId(),
                "title" => $this->getTitle(),
                'configuration' => $this->getConfiguration()
            )
        );
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderManagerBundle:Element:overview.html.twig';
    }

    /**
     * @inheritdoc
     */
    public function denormalizeConfiguration(array $configuration, Mapper $mapper)
    {
        if (isset($configuration['layerset'])) {
            $configuration['layerset'] = $mapper->getIdentFromMapper(
                'Mapbender\CoreBundle\Entity\Layerset',
                intval($configuration['layerset']),
                true
            );
        }
        return $configuration;
    }
}
