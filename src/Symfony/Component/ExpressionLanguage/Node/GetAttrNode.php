<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Node;

use Symfony\Component\ExpressionLanguage\Compiler;
use Symfony\Component\PropertyAccess\PropertyAccess;

class GetAttrNode extends Node
{
    const PROPERTY_CALL = 1;
    const METHOD_CALL = 2;
    const ARRAY_CALL = 3;

    public function __construct(Node $node, Node $attribute, ArrayNode $arguments, $type)
    {
        parent::__construct(
            array('node' => $node, 'attribute' => $attribute, 'arguments' => $arguments),
            array('type' => $type)
        );
    }

    public function compile(Compiler $compiler)
    {
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:

                $property = $this->nodes['attribute']->attributes['value'];
                $accessor = PropertyAccess::createPropertyAccessor();
                $objName = $this->nodes['node']->attributes['name'];
                $obj = $compiler->getFunction($objName);

                // scope may be injected in compiler
                if(is_array($obj) && isset($obj['compiler'])) {
                    if(isset($obj['compiler']) && $obj['compiler'] instanceof Compiler) {
                        $obj = $obj['compiler']->getFunction($objName);
                    }
                }

                $compiler
                    ->compile($this->nodes['node'])
                    ->raw($accessor->getAccessorPath($obj, $property));
                ;
                break;

            case self::METHOD_CALL:
                $compiler
                    ->compile($this->nodes['node'])
                    ->raw('->')
                    ->raw($this->nodes['attribute']->attributes['value'])
                    ->raw('(')
                    ->compile($this->nodes['arguments'])
                    ->raw(')')
                ;
                break;

            case self::ARRAY_CALL:
                $compiler
                    ->compile($this->nodes['node'])
                    ->raw('[')
                    ->compile($this->nodes['attribute'])->raw(']')
                ;
                break;
        }
    }

    public function evaluate($functions, $values)
    {
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                $obj = $this->nodes['node']->evaluate($functions, $values);
                if (!is_object($obj)) {
                    throw new \RuntimeException('Unable to get a property on a non-object.');
                }

                $property = $this->nodes['attribute']->attributes['value'];
                $accessor = PropertyAccess::createPropertyAccessor();

                return $accessor->getValue($obj, $property);

            case self::METHOD_CALL:
                $obj = $this->nodes['node']->evaluate($functions, $values);
                if (!is_object($obj)) {
                    throw new \RuntimeException('Unable to get a property on a non-object.');
                }

                return call_user_func_array(array($obj, $this->nodes['attribute']->evaluate($functions, $values)), $this->nodes['arguments']->evaluate($functions, $values));

            case self::ARRAY_CALL:
                $values = $this->nodes['node']->evaluate($functions, $values);
                if (!is_array($values) && !$values instanceof \ArrayAccess) {
                    throw new \RuntimeException('Unable to get an item on a non-array.');
                }

                return $values[$this->nodes['attribute']->evaluate($functions, $values)];
        }
    }
}
