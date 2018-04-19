<?php

namespace Mapbender\HroBundle\Element;

use Doctrine\ORM\EntityManager;
use Mapbender\CoreBundle\Component\Element;
use Mapbender\CoreBundle\Entity\Element as Entity;
use Mapbender\CoreBundle\Entity\Application as AppEntity;

/**
 * Map's overview element
 *
 * @author Paul Schmidt
 */
class BaseSourceSwitcher extends Element
{

    /**
     * @inheritdoc
     */
    public static function getClassTitle()
    {
        return "HRO BaseSourceSwitcher";
    }

    /**
     * @inheritdoc
     */
    public static function getClassDescription()
    {
        return "mb.core.basesourceswitcher.class.Description";
    }

    /**
     * @inheritdoc
     */
    public static function getClassTags()
    {
        return array(
            "mb.core.basesourceswitcher.tag.base",
            "mb.core.basesourceswitcher.tag.source",
            "mb.core.basesourceswitcher.tag.switcher");
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array(
            'tooltip' => "BaseSourceSwitcher",
            'target' => null,
            'display'  => null,
            'cprTitle' => null,
            'cprUrl' => null,
            'instancesets' => array()
        );
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbBaseSourceSwitcher';
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\HroBundle\Element\Type\BaseSourceSwitcherAdminType';
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderHroBundle:ElementAdmin:basesourceswitcher.html.twig';
    }

    /**
     * @inheritdoc
     */
    public static function listAssets()
    {
        return array(
            'js' => array('mapbender.element.basesourceswitcher.js'),
            'css' => array('@MapbenderHroBundle/Resources/public/sass/element/basesourceswitcher.scss')
        );
    }
    
    /**
     * @inheritdoc
     */
    public function getConfiguration()
    {
        $configuration = $confHelp = parent::getConfiguration();
        if (isset($configuration['instancesets'])) {
            unset($configuration['instancesets']);
        }
        $configuration['groups'] = array();
        foreach ($confHelp['instancesets'] as $instanceset) {
            if (isset($instanceset['group']) && $instanceset['group'] !== '') {
                if (!isset($configuration['groups'][$instanceset['group']])) {
                    $configuration['groups'][$instanceset['group']] = array();
                }
                $configuration['groups'][$instanceset['group']][] = array(
                    'title' => $instanceset['title'],
                    'sources' => $instanceset['instances'],
                    'cprTitle' => $instanceset['cprTitle'],
                    'cprUrl' => $instanceset['cprUrl']
                );
            } else {
                $configuration['groups'][$instanceset['title']] = array(
                    'title' => $instanceset['title'],
                    'sources' => $instanceset['instances'],
                    'cprTitle' => $instanceset['cprTitle'],
                    'cprUrl' => $instanceset['cprUrl']
                );
            }
        }
        return $configuration;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return $this->container->get('templating')->render(
            'MapbenderHroBundle:Element:basesourceswitcher.html.twig',
            array(
                'id' => $this->getId(),
                "title" => $this->getTitle(),
                'configuration' => $this->getConfiguration())
        );
    }

    /**
     * @inheritdoc
     */
    public function copyConfiguration(EntityManager $em, AppEntity &$copiedApp, &$elementsMap, &$layersetMap)
    {
        $subElements = array();
        $toOverwrite = array();
        $sourcesets = array();
        $form = Element::getElementForm($this->container, $this->application->getEntity(), $this->entity);
        // overwrite
        foreach ($form['form']['configuration']->all() as $fieldName => $fieldValue) {
            $norm = $fieldValue->getNormData();
            if ($fieldName === 'sourcesets') {
                $help = array();
                foreach ($layersetMap as $layersetId => $layerset) {
                    foreach ($layerset['instanceMap'] as $old => $new) {
                        $help[$old] = $new;
                    }
                }
                $sourcesets = $norm;
                foreach ($norm as $key => $value) {
                    $nsources = array();
                    foreach ($value['sources'] as $instId) {
                        if (key_exists(strval($instId), $help)) {
                            $nsources[] = $help[strval($instId)];
                        }
                    }
                    $sourcesets[$key]['sources'] = $nsources;
                }
            } elseif ($norm instanceof Entity) { // Element only target ???
                $subElements[$fieldName] = $norm->getId();

                $fv = $form['form']->createView();
            }
        }
        $copiedElm = $elementsMap[$this->entity->getId()];
        if (count($toOverwrite) > 0) {
            $configuration = $this->entity->getConfiguration();
            foreach ($toOverwrite as $key => $value) {
                $configuration[$key] = $value;
            }
            $copiedElm->setConfiguration($configuration);
        }
        if (count($subElements) > 0) {
            foreach ($subElements as $name => $value) {
                $configuration = $copiedElm->getConfiguration();
                $targetId = null;
                if ($value !== null) {
                    $targetId = $elementsMap[$value]->getId();
                }
                $configuration[$name] = $targetId;
                $copiedElm->setConfiguration($configuration);
            }
        }
        $configuration = $copiedElm->getConfiguration();
        $configuration['sourcesets'] = $sourcesets;
        $copiedElm->setConfiguration($configuration);
        return $copiedElm;
    }
}
