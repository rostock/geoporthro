<?php
namespace Mapbender\CoreBundle\Element;

use Mapbender\CoreBundle\Component\Element;

/**
 * Sketch Element
 * 
 * @author Paul Schmidt
 */
class Sketch extends Element
{

    /**
     * @inheritdoc
     */
    public static function getClassTitle()
    {
        return "mb.core.sketch.class.title";
    }

    /**
     * @inheritdoc
     */
    public static function getClassDescription()
    {
        return "mb.core.sketch.class.description";
    }

    /**
     * @inheritdoc
     */
    public static function getClassTags()
    {
        return array(
            "mb.core.sketch.tag.sketch",
            "mb.core.sketch.tag.circle");
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\CoreBundle\Element\Type\SketchAdminType';
    }

    /**
     * @inheritdoc
     */
    static public function listAssets()
    {
        return array(
            'js' => array('mapbender.element.sketch.js'),
            'css' => array(),
            'trans' => array('MapbenderCoreBundle:Element:sketch.json.twig')
        );
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array(
            "tooltip" => "Sketch",
            "target" => null,
            "defaultType" => null,
            "types" => null
        );
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbSketch';
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return $this->container->get('templating')
                ->render('MapbenderCoreBundle:Element:sketch.html.twig',
                    array(
                    'id' => $this->getId(),
                    "title" => $this->getTitle(),
                    'configuration' => $this->getConfiguration()));
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderCoreBundle:ElementAdmin:sketch.html.twig';
    }

    public function httpAction($action)
    {
        // TODO access (acl)

        switch ($action) {
            case 'getForm':
                return $this->getForm();
                break;
            default:
                throw new NotFoundHttpException('No such action');
        }
    }

    protected function getForm()
    {
        
        $html = $this->container->get('templating')
            ->render('MapbenderCoreBundle:Form:sketch-form.html.twig',
            array(
            'id' => $this->getId(),
            "title" => $this->getTitle(),
            'configuration' => $this->getConfiguration()));
        return new Response($html, 200, array('Content-Type' => 'text/html'));
    }

    protected function saveForm()
    {
        $request = $this->container->get('request');
        // TODO save form
        // 
        // if ok
        return new Response(json_encode(array("success" => MyContent)), 200,
            array('Content-Type' => 'application/json'));
        // otherwise
        return new Response(json_encode(array(
                "error" => DefineErrorAtTransTwig)), 200,
            array('Content-Type' => 'application/json'));
    }

}