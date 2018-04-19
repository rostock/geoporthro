<?php

/**
 * TODO: License
 */

namespace Mapbender\CoreBundle\Component;

use FOM\ManagerBundle\Component\ManagerBundle;

/**
 * The base bundle class for all Mapbender3 bundles.
 *
 * Mapbender3 bundles are special in a way as they expose lists of their
 * elements, layers and templates for the central Mapbender3 service, which
 * aggregates these for use in the manager backend.
 *
 * @author Christian Wygoda
 */
class MapbenderBundle
        extends ManagerBundle
{

    /**
     * Return list of element classes provided by this bundle.
     * Each entry in the array is a fully qualified class name.
     *
     * @return array Array of element class names
     */
    public function getElements()
    {
        return array();
    }

    /**
     * Return list of layer classes provided by this bundle.
     * Each entry in the array is a fully qualified class name.
     *
     * @return array Array of layer class names
     */
    public function getLayers()
    {
        return array();
    }

    /**
     * Return list of template classes provided by this bundle.
     * Each entry in the array is a fully qualified class name.
     *
     * @return array() Array of template class names
     */
    public function getTemplates()
    {
        return array();
    }

    /**
     * @inheritdoc
     */
    public function getManagerControllers()
    {
        
    }

    /**
     * Source factories provide information about source importers/parsers/transformers
     */
    public function getRepositoryManagers()
    {
        return array();
    }

}

