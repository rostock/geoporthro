<?php

/**
 * TODO: License
 */

namespace Mapbender\CoreBundle\Component;

use Mapbender\CoreBundle\Entity\Application as ApplicationEntity;
use Mapbender\CoreBundle\Entity\Element;
use Mapbender\CoreBundle\Component\Element as ElementComponent;
use Mapbender\CoreBundle\Entity\Layerset;
use Mapbender\CoreBundle\Entity\RegionProperties;
//use Mapbender\CoreBundle\Entity\Layer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * YAML mapper for applications
 *
 * This class is responsible for mapping application definitions given in the
 * YAML configuration to Application configuration entities.
 *
 * @author Christian Wygoda
 */
class ApplicationYAMLMapper
{

    /**
     * The service container
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get all YAML applications
     *
     * @return array
     */
    public function getApplications()
    {
        $definitions = $this->container->getParameter('applications');

        $applications = array();
        foreach ($definitions as $slug => $def) {
            $application = $this->getApplication($slug);
            if ($application !== null) {
                $applications[] = $application;
            }
        }

        return $applications;
    }

    /**
     * Get YAML application for given slug
     *
     * Will return null if no YAML application for the given slug exists.
     *
     * @param string $slug
     * @return Application
     */
    public function getApplication($slug)
    {
        $definitions = $this->container->getParameter('applications');
        if (!array_key_exists($slug, $definitions)) {
            return null;
        }
        $timestamp = round((microtime(true) * 1000));
        $definition = $definitions[$slug];
        if (!key_exists('title', $definition)) {
            $definition['title'] = "TITLE " . $timestamp;
        }

        if (!key_exists('published', $definition)) {
            $definition['published'] = false;
        } else {
            $definition['published'] = (boolean) $definition['published'];
        }

        if (!key_exists('listed', $definition)) {
            $definition['listed'] = false;
        } else {
            $definition['listed'] = (boolean) $definition['listed'];
        }

        // First, create an application entity
        $application = new ApplicationEntity();
        $application
                ->setScreenshot(key_exists("screenshot", $definition) ? $definition['screenshot'] : null)
                ->setSlug($slug)
                ->setTitle(isset($definition['title'])?$definition['title']:'')
                ->setDescription(isset($definition['description'])?$definition['description']:'')
                ->setTemplate($definition['template'])
                ->setExcludeFromList(isset($definition['excludeFromList'])?$definition['excludeFromList']:false)
                ->setPublished($definition['published'])
                ->setListed($definition['listed']);
        if (isset($definition['custom_css'])) {
            $application->setCustomCss($definition['custom_css']);
        }

        if (isset($definition['publicOptions'])) {
            $application->setPublicOptions($definition['publicOptions']);
        }

        if (isset($definition['publicOptions'])) {
            $application->setPublicOptions($definition['publicOptions']);
        }

        if (array_key_exists('extra_assets', $definition)) {
            $application->setExtraAssets($definition['extra_assets']);
        }
        if (key_exists('regionProperties', $definition)) {
            foreach ($definition['regionProperties'] as $regProps) {
                $regionProperties = new RegionProperties();
                $regionProperties->setName($regProps['name']);
                $regionProperties->setProperties($regProps['properties']);
                $application->addRegionProperties($regionProperties);
            }
        }

        if (!isset($definition['elements'])) {
            $definition['elements'] = array();
        }

        // Then create elements
        foreach ($definition['elements'] as $region => $elementsDefinition) {
            $weight = 0;
            if ($elementsDefinition !== null) {
                foreach ($elementsDefinition as $id => $elementDefinition) {
                    /**
                     * MAP Layersets handling
                     */
                    if ($elementDefinition['class'] == "Mapbender\\CoreBundle\\Element\\Map") {
                        if (!isset($elementDefinition['layersets'])) {
                            $elementDefinition['layersets'] = array();
                        }
                        if (isset($elementDefinition['layerset'])) {
                            $elementDefinition['layersets'][] = $elementDefinition['layerset'];
                        }
                    }

                    $configuration_ = $elementDefinition;
                    unset($configuration_['class']);
                    unset($configuration_['title']);
                    $entity_class = $elementDefinition['class'];
                    $appl = new \Mapbender\CoreBundle\Component\Application($this->container, $application, array());
                    if (!class_exists($entity_class)) {
                        throw new \RuntimeException('Unknown Element class ' . $entity_class);
                    }
                    $elComp = new $entity_class($appl, $this->container, new \Mapbender\CoreBundle\Entity\Element());

                    $elm_class = get_class($elComp);
                    if ($elm_class::$merge_configurations) {
                        $configuration =
                            ElementComponent::mergeArrays($elComp->getDefaultConfiguration(), $configuration_, array());
                    } else {
                        $configuration = $configuration_;
                    }

                    $class = $elementDefinition['class'];
                    $title = array_key_exists('title', $elementDefinition) ?
                            $elementDefinition['title'] :
                            $class::getClassTitle();

                    $element = new Element();

                    $element->setId($id)
                            ->setClass($elementDefinition['class'])
                            ->setTitle($title)
                            ->setConfiguration($configuration)
                            ->setRegion($region)
                            ->setWeight($weight++)
                            ->setApplication($application);

                    // set Roles
                    $element->yaml_roles = array();
                    if (array_key_exists('roles', $elementDefinition)) {
                        $element->yaml_roles = $elementDefinition['roles'];
                    }
                    $application->addElements($element);
                }
            }
        }

        $application->yaml_roles = array();
        if (array_key_exists('roles', $definition)) {
            $application->yaml_roles = $definition['roles'];
        }

        if (!isset($definition['layersets'])) {
            $definition['layersets'] = array();

            /**
             * @deprecated definition
             */
            if (isset($definition['layerset'])) {
                $definition['layersets'][] = $definition['layerset'];
            }
        }

        // TODO: Add roles, entity needs work first
        // Create layersets and layers
        /** @var SourceInstanceEntityHandler $entityHandler */
        foreach ($definition['layersets'] as $id => $layerDefinitions) {
            $layerset = new Layerset();
            $layerset
                ->setId($id)
                ->setTitle('YAML - ' . $id)
                ->setApplication($application);

            $weight = 0;
            foreach ($layerDefinitions as $id => $layerDefinition) {
                $class = $layerDefinition['class'];
                unset($layerDefinition['class']);
                $entityHandler    = EntityHandler::createHandler($this->container, new $class());
                $instance         = $entityHandler->getEntity();
                $internDefinition = array(
                    'weight'   => $weight++,
                    "id"       => $id,
                    "layerset" => $layerset
                );
                $entityHandler->setParameters(array_merge($layerDefinition, $internDefinition));
                $layerset->addInstance($instance);
            }
            $application->addLayerset($layerset);
        }

        $application->setSource(ApplicationEntity::SOURCE_YAML);

        return $application;
    }
}
