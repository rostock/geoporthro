<?php
namespace Mapbender\WmcBundle\Component;

use Mapbender\CoreBundle\Component\Application;
use Mapbender\CoreBundle\Component\Element;
use Mapbender\CoreBundle\Utils\UrlUtil;
use Mapbender\CoreBundle\Entity\State;
use Mapbender\WmcBundle\Entity\Wmc;

class WmcHandler
{
    public static $WMC_DIR = "wmc";
    protected $element;
    protected $container;
    protected $application;

    /**
     * Creates a wmc handler
     *
     * @param Element $element
     */
    public function __construct(Element $element, $application, $container)
    {
        $this->element = $element;
        $this->application = $application;
        $this->container = $container;
    }

    /**
     * Returns a state from a state id
     *
     * @return Mapbender\CoreBundle\Entity\State or null.
     */
    public function findState($stateid)
    {
        $state = null;
        if ($stateid) {
            $state = $this->container->get('doctrine')
                ->getRepository('Mapbender\CoreBundle\Entity\State')
                ->find($stateid);
        }
        return $this->signUrls($state);
    }

    /**
     * Saves and returns a saved state
     *
     * @param array $jsonState a mapbender state
     * @return \Mapbender\CoreBundle\Entity\State or null
     */
    public function saveState($jsonState)
    {
        $state = null;
        if ($jsonState !== null) {
            $state = new State();
            $state->setServerurl($this->getBaseUrl());
            $state->setSlug($this->application->getSlug());
            $state->setTitle("SuggestMap");
            $state->setJson($jsonState);
            $state = $this->unSignUrls($state);
            $em = $this->container->get('doctrine')->getManager();
            $em->persist($state);
            $em->flush();
        }
        return $state;
    }

    /**
     * Returns a wmc.
     * @param integer $wmcid a Wmc id
     *
     * @return Wmc or null.
     */
    public function getWmc($wmcid, $onlyPublic = TRUE)
    {
        $query = $this->container->get('doctrine')->getManager()
            ->createQuery("SELECT wmc FROM MapbenderWmcBundle:Wmc wmc"
                . " JOIN wmc.state s Where"
//		. " s.slug IN (:slug) AND"
                . " wmc.id=:wmcid"
                . ($onlyPublic === TRUE ? " AND wmc.public = :public" : "")
                . " ORDER BY wmc.id ASC")
//	    ->setParameter('slug', array($this->application->getSlug()))
            ->setParameter('wmcid', $wmcid);
        if($onlyPublic) $query->setParameter('public', true);
        $wmc = $query->getResult();
        if ($wmc && count($wmc) === 1) {
            $wmc_signed = $wmc[0];
            $wmc_signed->setState($this->signUrls($wmc_signed->getState()));
            return $wmc_signed;
        } else {
            return null;
        }
    }

    /**
     * Returns a wmc list
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getWmcList($onlyPublic = true)
    {
        $query = $this->container->get('doctrine')->getManager()
            ->createQuery("SELECT wmc FROM MapbenderWmcBundle:Wmc wmc"
                . " JOIN wmc.state s Where s.slug IN (:slug)"
                . ($onlyPublic === TRUE ? " AND wmc.public=:public" : "")
                . " ORDER BY wmc.id ASC")
            ->setParameter('slug', array($this->application->getSlug()));
        if($onlyPublic) $query->setParameter('public', true);
        return $query->getResult();
    }

    /**
     * Gets a base url
     *
     * @return string a base url
     */
    public function getBaseUrl()
    {
        $request = $this->container->get('request');
        $url_base = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();
        return $url_base;
    }

    /**
     * Gets a url to wmc directory or to file with "$filename
     *
     * @param string $filename
     * @return string a url to wmc directory or to file with "$filename"
     */
    public function getWmcUrl($filename = null)
    {
        $url_base = Application::getAppWebUrl($this->container, $this->application->getSlug());
        $url_wmc = $url_base . '/' . WmcHandler::$WMC_DIR;
        if ($filename !== null) {
            return $url_wmc . '/' . $filename;
        } else {
            return $url_wmc;
        }
    }

    /**
     * Gets a path to wmc directory
     *
     * @return string|null path to wmc directory or null
     */
    public function getWmcDir()
    {
        $uploads_dir = Application::getAppWebDir($this->container, $this->application->getSlug());
        $wmc_dir = $uploads_dir . '/' . WmcHandler::$WMC_DIR;
        if (!is_dir($wmc_dir)) {
            if (mkdir($wmc_dir)) {
                return $wmc_dir;
            } else {
                return null;
            }
        } else {
            return $wmc_dir;
        }
    }
 
    protected static function unsignUrl($urlIn)
    {
        return UrlUtil::validateUrl($urlIn, array(), array(strtolower('_signature')));
    }

    /**
     * @param \Mapbender\CoreBundle\Component\Signer $signer
     * @param string $urlIn
     * @return string
     */
    protected static function signUrl($signer, $urlIn)
    {
        return $signer->signUrl(static::unsignUrl($urlIn));
    }

    public function unSignUrls(State $state){
        $json = json_decode($state->getJson(), true);
        if ($json && isset($json['sources']) && is_array($json['sources'])) {
            foreach ($json['sources'] as &$source) {
                if ($source['type'] == "wmts") {
                    // WMTS source URLs are configured in a different place
                    foreach ($source['configuration']['layers'] as &$layerConfig) {
                        $layerConfig['options']['url'] = $this->unsignUrl($layerConfig['options']['url']);
                    }
                } else {
                    $source['configuration']['options']['url'] =
                        $this->unsignUrl($source['configuration']['options']['url']);
                }
            }
        }
        $state->setJson(json_encode($json));
        return $state;
    }

    public function signUrls(State $state){
        $state->getId();
        $json = json_decode($state->getJson(), true);
        if($json && isset($json['sources']) && is_array($json['sources'])){
            $signer = $this->container->get('signer');
            foreach($json['sources'] as &$source){
                if ($source['type'] == "wmts") {
                    // WMTS source URLs are configured in a different place
                    foreach ($source['configuration']['layers'] as &$layerConfig) {
                        $layerConfig['options']['url'] = $this->signUrl($signer, $layerConfig['options']['url']);
                    }
                } else {
                    $source['configuration']['options']['url'] =
                            $this->signUrl($signer, $source['configuration']['options']['url']);
                }
            }
        }
        $state->setJson(json_encode($json));
        return $state;
    }

}
