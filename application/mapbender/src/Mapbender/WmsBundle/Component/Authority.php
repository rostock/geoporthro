<?php

namespace Mapbender\WmsBundle\Component;

/**
 * Authority class.
 *
 * @author Paul Schmidt
 */
class Authority
{

    /**
     * ORM\Column(type="string", nullable=true)
     */
    //@TODO Doctrine bug: "protected" replaced with "public"
    public $url;

    /**
     * ORM\Column(type="string", nullable=true)
     */
    //@TODO Doctrine bug: "protected" replaced with "public"
    public $name;

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set url
     * @param string $value
     * @return Authority
     */
    public function setUrl($value)
    {
        $this->url = $value;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set name
     * @param string $value
     * @return Authority
     */
    public function setName($value)
    {
        $this->name = $value;
        return $this;
    }
}
