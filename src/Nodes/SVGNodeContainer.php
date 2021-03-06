<?php

namespace SVG\Nodes;

use SVG\Nodes\Structures\SVGStyle;
use SVG\Rasterization\SVGRasterizer;
use SVG\Utilities\SVGStyleParser;
use SVG\Rasterization\Path\SVGPathParser;
use SVG\Rasterization\Path\SVGPathApproximator;

/**
 * Represents an SVG image element that contains child elements.
 */
abstract class SVGNodeContainer extends SVGNode
{
    /** @var SVGNode[] $children This node's child nodes. */
    protected $children;

    /**
     * @var string[] $globalStyles A 2D array mapping CSS selectors to values.
     */
    protected $containerStyles;

    public function __construct()
    {
        parent::__construct();

        $this->containerStyles = array();
        $this->children = array();
    }

    /**
     * Adds an SVGNode instance to the end of this container's child list.
     * Does nothing if it already exists.
     *
     * @param SVGNode $node The node to add to this container's children.
     *
     * @return $this This node instance, for call chaining.
     */
    public function addChild(SVGNode $node)
    {
        if ($node === $this || $node->parent === $this) {
            return $this;
        }

        if (isset($node->parent)) {
            $node->parent->removeChild($node);
        }

        $this->children[] = $node;
        $node->parent     = $this;

        if ($node instanceof SVGStyle) {
            // if node is SVGStyle then add rules to container's style
            $this->addContainerStyle($node);
        }

        return $this;
    }

    /**
     * Removes a child node, given either as its instance or as the index it's
     * located at, from this container.
     *
     * @param SVGNode|int $nodeOrIndex The node (or respective index) to remove.
     *
     * @return $this This node instance, for call chaining.
     */
    public function removeChild($nodeOrIndex)
    {
        $index = $this->resolveChildIndex($nodeOrIndex);
        if ($index === false) {
            return $this;
        }

        $node         = $this->children[$index];
        $node->parent = null;

        array_splice($this->children, $index, 1);

        return $this;
    }

    /**
     * Resolves a child node to its index. If an index is given, it is returned
     * without modification.
     *
     * @param SVGNode|int $nodeOrIndex The node (or respective index).
     *
     * @return int|false The index, or false if argument invalid or not a child.
     */
    private function resolveChildIndex($nodeOrIndex)
    {
        if (is_int($nodeOrIndex)) {
            return $nodeOrIndex;
        } elseif ($nodeOrIndex instanceof SVGNode) {
            return array_search($nodeOrIndex, $this->children, true);
        }

        return false;
    }

    public function findChildNodeById($id)
    {
        return $this->filterChildrenForId($this, $id);
    }//end findChildNodeById()

    private function filterChildrenForId($node, $id)
    {
        $ret = false;

        if ((string) $node->getAttribute('id') === $id) {
            return $node;
        }

        if (false === property_exists($node, 'children')) {
            return false;
        }

        foreach ($node->children as $child) {
            if ((string) $child->getAttribute('id') === $id) {
                return $child;
            }

            if (false === property_exists($child, 'children')) {
                continue;
            }

            if (count($child->children) > 0) {
                foreach ($child->children as $grandChild) {
                    $ret = $this->filterChildrenForId($grandChild, $id);

                    if (false !== $ret) {
                        return $ret;
                    }
                }
            }
        }

        return $ret;
    }//end filterChildrenForId()

    public function guessWidth()
    {
        return $this->guessDimension('width');
    }//end guessWidth()

    public function guessHeight()
    {
        return $this->guessDimension('height');
    }//end guessHeight()

    public function guessDimension($dimension = 'width')
    {
        $measurement = 0;

        if ($dimension === 'width') {
            $coordinateKey = 0;
            $letter        = 'X';
        }

        if ($dimension === 'height') {
            $coordinateKey = 1;
            $letter        = 'Y';
        }

        $getDimension  = 'get'.ucfirst($dimension);
        $getCoordinate = 'get'.$letter;

        if (false === property_exists($this, 'children')) {
            return $measurement;
        }

        foreach ($this->children as $child) {
            $nodeName = $child->getName();

            switch ($nodeName) {
                case 'rect':
                    $measurement = ($child->$getCoordinate() + $child->$getDimension());
                    break;

                case 'path':
                    $pathDesc    = $child->getDescription();
                    $parser      = new SVGPathParser();
                    $commands    = $parser->parse($pathDesc);
                    $approxer    = new SVGPathApproximator();
                    $coordinates = $approxer->approximate($commands);

                    foreach ($coordinates as $coordinateGroups) {
                        foreach ($coordinateGroups as $coordinate) {
                            if ($coordinate[$coordinateKey] > $measurement) {
                                $measurement = $coordinate[$coordinateKey];
                            }
                        }
                    }
                    break;

                case 'g':
                    if ($measurement < $child->guessDimension($dimension)) {
                        $measurement = $child->guessDimension($dimension);
                    }

                    break;

                default:
                    // There's no guess.
                    break;
            }
        }

        return $measurement;
    }//end guessDimension()

    public function removeGroupTransforms()
    {

        if (false === property_exists($this, 'children')) {
            return;
        }

        if (null !== $this->getAttribute('transform')) {
            $this->removeAttribute('transform', '');
        }

        foreach ($this->children as $child) {
            if ('g' === $child->getName()) {
                if (null !== $child->getAttribute('transform')) {
                    $child->removeAttribute('transform', '');
                }

                $child->removeGroupTransforms();
            }
        }
    }//end removeGroupTransforms()

    /**
     * @return int The amount of children in this container.
     */
    public function countChildren()
    {
        return count($this->children);
    }

    /**
     * @return SVGNode The child node at the given index.
     */
    public function getChild($index)
    {
        return $this->children[$index];
    }

    /**
     * Adds the SVGStyle element rules to container's styles.
     *
     * @param SVGStyle $styleNode The style node to add rules from.
     *
     * @return $this This node instance, for call chaining.
     */
    public function addContainerStyle(SVGStyle $styleNode)
    {
        $newStyles = SVGStyleParser::parseCss($styleNode->getCss());
        $this->containerStyles = array_merge($this->containerStyles, $newStyles);

        return $this;
    }


    public function rasterize(SVGRasterizer $rasterizer)
    {
        if ($this->getComputedStyle('display') === 'none') {
            return;
        }

        // 'visibility' can be overridden -> only applied in shape nodes.

        foreach ($this->children as $child) {
            $child->rasterize($rasterizer);
        }
    }

    /**
     * Returns a node's 'global' style rules.
     *
     * @param SVGNode $node The node for which we need to obtain.
     * its container style rules.
     *
     * @return string[] The style rules to be applied.
     */
    public function getContainerStyleForNode(SVGNode $node)
    {
        $pattern = $node->getIdAndClassPattern();

        return $this->getContainerStyleByPattern($pattern);
    }

    /**
     * Returns style rules for the given node id + class pattern.
     *
     * @param string $pattern The node's pattern.
     *
     * @return string[] The style rules to be applied.
     */
    public function getContainerStyleByPattern($pattern)
    {
        if ($pattern === null) {
            return array();
        }

        $nodeStyles = array();
        if (!empty($this->parent)) {
            $nodeStyles = $this->parent->getContainerStyleByPattern($pattern);
        }

        $keys = $this->pregGrepStyle($pattern);
        foreach ($keys as $key) {
            $nodeStyles = array_merge($nodeStyles, $this->containerStyles[$key]);
        }

        return $nodeStyles;
    }

    /**
     * Returns the array consisting of the keys of the style rules that match
     * the given pattern.
     *
     * @param string $pattern The pattern to search for.
     *
     * @return string[] The matches array
     */
    private function pregGrepStyle($pattern)
    {
        return preg_grep($pattern, array_keys($this->containerStyles));
    }
}
