<?php

namespace SVG\Nodes\Text;

use SVG\Nodes\SVGNode;
use SVG\Rasterization\SVGRasterizer;

class SVGTspan extends SVGNode
{
    const TAG_NAME = 'tspan';

    /**
     * Text of the node.
     *
     * @var string
     */
    public $text;

    public function __construct($text, $x = 0, $y = 0)
    {
        parent::__construct();

        $this->text = $text;
        $this->setAttributeOptional('x', $x);
        $this->setAttributeOptional('y', $y);
    }//end __construct()

    public static function constructFromAttributes($attrs)
    {
        /**
         * XML Node.
         *
         * @var \SimpleXMLElement
         */
        $xmlNode = $attrs;

        $text = (string) $xmlNode;

        return new static($text);
    }//end constructFromAttributes()

    public function rasterize(SVGRasterizer $rasterizer)
    {
        return null;
    }//end rasterize()

    /**
     * @return string The x coordinate of the upper left corner.
     */
    public function getX()
    {
        return $this->getAttribute('x');
    }

    /**
     * Sets the x coordinate of the upper left corner.
     *
     * @param string $x The new coordinate.
     *
     * @return $this This node instance, for call chaining.
     */
    public function setX($x)
    {
        return $this->setAttribute('x', $x);
    }

    /**
     * @return string The y coordinate of the upper left corner.
     */
    public function getY()
    {
        return $this->getAttribute('y');
    }

    /**
     * Sets the y coordinate of the upper left corner.
     *
     * @param string $y The new coordinate.
     *
     * @return $this This node instance, for call chaining.
     */
    public function setY($y)
    {
        return $this->setAttribute('y', $y);
    }
}//end class
