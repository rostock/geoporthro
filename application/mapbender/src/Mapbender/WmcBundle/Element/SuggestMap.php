<?php
namespace Mapbender\WmcBundle\Element;

use Mapbender\CoreBundle\Component\Element;
use Mapbender\WmcBundle\Component\WmcHandler;
use Symfony\Component\HttpFoundation\Response;

class SuggestMap extends Element
{

    /**
     * @inheritdoc
     */
    static public function getClassTitle()
    {
        return "mb.wmc.suggestmap.class.title";
    }

    /**
     * @inheritdoc
     */
    static public function getClassDescription()
    {
        return "mb.wmc.suggestmap.class.description";
    }

    /**
     * @inheritdoc
     */
    static public function getClassTags()
    {
        return array("mb.wmc.suggestmap.suggest", "mb.wmc.suggestmap.map");
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultConfiguration()
    {
        return array(
            "tooltip" => null,
            "target" => null,
            "receiver" => array('email'),
        );
    }

    /**
     * @inheritdoc
     */
    public static function getType()
    {
        return 'Mapbender\WmcBundle\Element\Type\SuggestMapAdminType';
    }

    /**
     * @inheritdoc
     */
    public static function getFormTemplate()
    {
        return 'MapbenderWmcBundle:ElementAdmin:suggestmap.html.twig';
    }

    /**
     * @inheritdoc
     */
    public function getWidgetName()
    {
        return 'mapbender.mbSuggestMap';
    }

    /**
     * @inheritdoc
     */
    static public function listAssets()
    {
        $js = array(
            'mapbender.element.suggestmap.js',
            '@FOMCoreBundle/Resources/public/js/widgets/popup.js',
            '@MapbenderCoreBundle/Resources/public/mapbender.social_media_connector.js'
        );
        return array(
            'js' => $js,
            'css' => array(
                'sass/element/suggestmap.scss'),
            'trans' => array(
                'MapbenderWmcBundle:Element:suggestmap.json.twig',
                'MapbenderWmcBundle:Element:wmchandler.json.twig')
        );
    }

    /**
     * @inheritdoc
     */
    public function getConfiguration()
    {
        $configuration = parent::getConfiguration();
        $stateid = $this->container->get('request')->get('stateid');
        if ($stateid) {
            $configuration["load"] = array('stateid' => $stateid);
        }
        return $configuration;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $config = $this->getConfiguration();
        $html = $this->container->get('templating')
            ->render('MapbenderWmcBundle:Element:suggestmap.html.twig',
            array(
            'id' => $this->getId(),
            'configuration' => $config,
            'title' => $this->getTitle(),
        ));
        return $html;
    }

    /**
     * @inheritdoc
     */
    public function httpAction($action)
    {
        $session = $this->container->get("session");

        if ($session->get("proxyAllowed", false) !== true) {
            throw new AccessDeniedHttpException('You are not allowed to use this proxy without a session.');
        }
        switch ($action) {
            case 'load':
                $id = $this->container->get('request')->get("_id", null);
                return $this->loadState($id);
                break;
            case 'state':
                return $this->saveState();
                break;
            case 'content':
                return $this->getContent();
                break;
            default:
                throw new NotFoundHttpException('No such action');
        }
    }

    protected function getContent()
    {
        $config = $this->getConfiguration();
        $html = $this->container->get('templating')
            ->render('MapbenderWmcBundle:Element:suggestmap-content.html.twig',
            array(
            'id' => $this->getId(),
            'configuration' => $config,
            'title' => $this->getTitle(),
        ));
        return new Response($html, 200, array('Content-Type' => 'text/html'));
    }

    /**
     * Returns a json encoded state
     *
     * @return \Symfony\Component\HttpFoundation\Response a json encoded result.
     */
    protected function loadState($stateid)
    {
        $wmchandler = new WmcHandler($this, $this->application, $this->container);
        $state = $wmchandler->findState($stateid);
        if ($state) {
            $id = $state->getId();
            return new Response(json_encode(array("data" => array($id => json_decode($state->getJson())))),
                200, array('Content-Type' => 'application/json'));
        } else {
            return new Response(json_encode(array("error" => $this->trans("mb.wmc.error.statenotfound",
                        array('%stateid%' => $stateid)))), 200,
                array('Content-Type' => 'application/json'));
        }
    }

    /**
     * Saves the mapbender state.
     *
     * @return \Symfony\Component\HttpFoundation\Response a json encoded result.
     */
    protected function saveState()
    {
        $wmchandler = new WmcHandler($this, $this->application, $this->container);
        $json = $this->container->get('request')->get("state", null);
        $state = $wmchandler->saveState($json);
        if ($state !== null) {
            return new Response(json_encode(array(
                    "id" => $state->getId())), 200,
                array('Content-Type' => 'application/json'));
        } else {
            return new Response(json_encode(array(
                    "error" => $this->trans("mb.wmc.error.statecannotbesaved"))),
                200, array('Content-Type' => 'application/json'));
        }
    }

}
