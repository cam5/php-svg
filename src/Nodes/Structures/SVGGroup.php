<?php

namespace SVG\Nodes\Structures;

use SVG\Nodes\SVGNodeContainer;

/**
 * Represents the SVG tag 'g'.
 */
class SVGGroup extends SVGNodeContainer
{
    const TAG_NAME = 'g';
    const X_OFFSET = 0;
    const Y_OFFSET = 1;

    public function __construct()
    {
        parent::__construct();
    }

    public function getOffset()
    {
        $match        = [];
        $transformVal = $this->getAttribute('transform');

        $validate = preg_match('/translate\((\d+\.\d+),\s?(\d+\.\d+)\)/', $transformVal, $match);

        if (1 !== $validate) {
            return false;
        }

        return [
                (float) $match[1],
                (float) $match[2],
        ];
    }//end getOffset()

    public function getXOffset()
    {
        $offset = $this->getOffset();

        if (false !== $this->getOffset()) {
            return $offset[self::X_OFFSET];
        }

        return false;
    }//end getXOffset()

    public function getYOffset()
    {
        $offset = $this->getOffset();

        if (false !== $this->getOffset()) {
            return $offset[self::Y_OFFSET];
        }

        return false;
    }//end getYOffset()
}//end class
