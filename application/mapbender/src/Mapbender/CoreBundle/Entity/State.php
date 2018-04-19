<?php

namespace Mapbender\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mapbender\CoreBundle\Component\Signer;

/**
 * Source entity
 *
 * @author Paul Schmidt
 *
 * @ORM\Entity
 * @ORM\Table(name="mb_core_state")
 */
class State
{

    /**
     * @var integer $id
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string $title The state title
     * @ORM\Column(type="string", length=128, nullable=true)
     */
    protected $title;

    /**
     * @var string $title The server url
     * @ORM\Column(type="string", length=1024, nullable=true)
     */
    protected $serverurl;

    /**
     * @var string $title The appllication slug
     * @ORM\Column(type="string", length=128, nullable=true)
     */
    protected $slug;

    /**
     * @var string $json The json
     * @ORM\Column(type="text", nullable=true)
     */
    protected $json;

    public function __construct()
    {

    }

    /**
     * Set id
     *
     * @param  integer $id
     * @return State
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set title
     *
     * @param  string $title
     * @return State
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set serverurl
     *
     * @param  string $serverurl
     * @return State
     */
    public function setServerurl($serverurl)
    {
        $this->serverurl = $serverurl;

        return $this;
    }

    /**
     * Get serverurl
     *
     * @return string serverurl
     */
    public function getServerurl()
    {
        return $this->serverurl;
    }

    /**
     * Set slug
     *
     * @param  string $slug
     * @return State
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set json
     *
     * @param  string $json
     * @return State
     */
    public function setJson($json)
    {
        $this->json = $json;

        return $this;
    }

    /**
     * Get json
     *
     * @return string
     */
    public function getJson()
    {
        return $this->json;
    }

    public function signSources(Signer $signer)
    {
        $json = json_decode($this->getJson(), true);
        if ($json && isset($json['sources']) && is_array($json['sources'])) {
            foreach ($json['sources'] as $source) {
                $source['configuration']['options']['url'] =
                    $signer->signUrl($source['configuration']['options']['url']);
            }
        }
    }
}
