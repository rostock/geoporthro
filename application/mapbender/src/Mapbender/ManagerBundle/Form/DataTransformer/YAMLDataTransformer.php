<?php

namespace Mapbender\ManagerBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Exception\DumpException;

/**
 * YAML <-> Array data transformer
 *
 * @author Christian Wygoda
 */
class YAMLDataTransformer implements DataTransformerInterface
{
    protected $indentLevel;

    public function __construct($indentLevel = 2)
    {
        $this->indentLevel = $indentLevel;
    }

    /**
     * Transforms array to YAML
     *
     * @param array $array
     * @return string
     */
    public function transform($array)
    {
        $dumper = new Dumper();

        try {
            $yaml = $dumper->dump($array, $this->indentLevel);
        } catch(DumpException $e) {
            throw new TransformationFailedException();
        }

        return $yaml;
    }

    /**
     * Transforms YAML to array
     *
     * @param string $yaml
     * @return array
     */
    public function reverseTransform($yaml)
    {
        $parser = new Parser();

        try {
            $array = $parser->parse($yaml);
        } catch(ParseException $e) {
            throw new TransformationFailedException();
        }

        return $array;
    }
}

