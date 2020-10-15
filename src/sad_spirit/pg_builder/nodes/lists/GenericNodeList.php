<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\{
    ElementParseable,
    Node,
    NodeList,
    Parseable,
    TreeWalker,
    nodes\GenericNode,
    exceptions\InvalidArgumentException
};

/**
 * An array that enforces the type of its elements
 *
 * Inspired by PEAR's PHP_ArrayOf class
 */
abstract class GenericNodeList extends GenericNode implements NodeList
{
    /**
     * Child nodes available through ArrayAccess
     * @var Node[]
     */
    protected $offsets = [];

    /**
     * Instances of these classes / interfaces will be allowed as list elements (Node is always checked)
     *
     * @return string[]
     */
    protected static function getAllowedElementClasses(): array
    {
        return [];
    }

    /**
     * Constructor, populates the list
     *
     * $list can be
     *  - an iterable containing "compatible" values
     *  - a string if Parser is available
     *
     * @param iterable|string|null $list
     */
    public function __construct($list = null)
    {
        $this->replace($list ?? []);
    }

    /**
     * Deep cloning of child nodes
     */
    public function __clone()
    {
        parent::__clone();
        foreach ($this->offsets as &$node) {
            $node = clone $node;
            if ($node instanceof GenericNode) {
                $node->parentNode = $this;
            } else {
                $node->setParentNode($this);
            }
        }
    }

    /**
     * GenericNodeList only serializes its $offsets property by default
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->offsets);
    }

    /**
     * GenericNodeList only unserializes its $offsets property by default
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $this->offsets = unserialize($serialized);
        $this->updateParentNodeOnOffsets();
    }

    /**
     * Restores the parent node link for array offsets on unserializing the object
     */
    protected function updateParentNodeOnOffsets(): void
    {
        foreach ($this->offsets as $node) {
            if ($node instanceof GenericNode) {
                $node->parentNode = $this;
            } else {
                $node->setParentNode($this);
            }
        }
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->offsets[$offset]);
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        if (isset($this->offsets[$offset])) {
            return $this->offsets[$offset];
        }

        throw new InvalidArgumentException("Undefined offset '{$offset}'");
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
    {
        $prepared = $this->prepareListElement($value);

        if (null === $offset) {
            $this->offsets[] = $prepared;
        } elseif (!isset($this->offsets[$offset])) {
            $this->offsets[$offset] = $prepared;
        } else {
            [$oldNode, $this->offsets[$offset]] = [$this->offsets[$offset], $prepared];
            if ($oldNode instanceof GenericNode) {
                $oldNode->parentNode = null;
            } else {
                $oldNode->setParentNode(null);
            }
        }
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetUnset($offset)
    {
        if (isset($this->offsets[$offset])) {
            if ($this->offsets[$offset] instanceof GenericNode) {
                $this->offsets[$offset]->parentNode = null;
            } else {
                $this->offsets[$offset]->setParentNode(null);
            }
            unset($this->offsets[$offset]);
        }
    }

    /**
     * Method required by Countable interface
     *
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->offsets);
    }

    /**
     * Method required by IteratorAggregate interface
     *
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->offsets);
    }

    /**
     * {@inheritDoc}
     */
    public function merge(...$lists): void
    {
        $prepared = [];
        foreach ($lists as $list) {
            $prepared[] = $this->convertToArray($list, __METHOD__);
        }

        $this->offsets = array_merge($this->offsets, ...$prepared);
    }

    /**
     * {@inheritDoc}
     */
    public function replace($list): void
    {
        $prepared = $this->convertToArray($list, __METHOD__);

        foreach ($this->offsets as $node) {
            $node->setParentNode(null);
        }
        $this->offsets = $prepared;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkGenericNodeList($this);
    }

    /**
     * {@inheritDoc}
     */
    public function replaceChild(Node $oldChild, Node $newChild): ?Node
    {
        if (
            null === ($result = parent::replaceChild($oldChild, $newChild))
            && false !== ($key = array_search($oldChild, $this->offsets, true))
        ) {
            $this->offsetSet($key, $newChild);
            // offsetSet() is expected to check the value itself
            return $newChild;
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function removeChild(Node $child): ?Node
    {
        if (
            null === ($result = parent::removeChild($child))
            && false !== ($key = array_search($child, $this->offsets, true))
        ) {
            $this->offsetUnset($key);
            return $child;
        }
        return $result;
    }

    /**
     * Ensures that "array-like" argument of merge() / replace() is either an iterable or a parseable string
     *
     * @param iterable|string $array
     * @param string          $method calling method, used only for Exception messages
     * @return iterable
     * @throws InvalidArgumentException
     */
    protected function prepareList($array, string $method): iterable
    {
        if (is_string($array) && $this instanceof Parseable) {
            $array = static::createFromString($this->getParserOrFail("an argument to '{$method}'"), $array);
        }
        if (!is_iterable($array)) {
            throw new InvalidArgumentException(sprintf(
                "%s requires either an array or an instance of Traversable, %s given",
                $method,
                is_object($array) ? 'object(' . get_class($array) . ')' : gettype($array)
            ));
        }

        return $array;
    }

    /**
     * Converts the "array-like" argument of merge() / replace() to an actual array
     *
     * The returned array should contain only instances of Node passed through prepareListElement(),
     * it is not checked further in merge() / replace()
     *
     * @param iterable|string $list
     * @param string $method
     * @return array
     */
    abstract protected function convertToArray($list, string $method): array;

    /**
     * Prepares the given value for addition to the list
     *
     * If the value is a string it is processed by Parser. The class / interface of value is checked against
     * the $allowedElementClasses.
     *
     * Finally, the list is set as a parent of Node. This is done here so that merge() / replace() methods
     * may work on an all or nothing principle, without possibility of merging only a part of array.
     *
     * @param mixed $value
     * @return Node
     */
    protected function prepareListElement($value): Node
    {
        if (is_string($value) && $this instanceof ElementParseable) {
            $value = $this->createElementFromString($value);
        }

        if (!$value instanceof Node) {
            throw new InvalidArgumentException(sprintf(
                "GenericNodeList can contain only instances of Node, %s given",
                is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }

        if ([] !== ($classes = static::getAllowedElementClasses())) {
            $found = false;
            foreach ($classes as $class) {
                if ($value instanceof $class) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $shortClasses = array_map(function ($className) {
                    return substr($className, strrpos($className, '\\') + 1);
                }, array_merge([get_class($this), get_class($value)], $classes));

                throw new InvalidArgumentException(sprintf(
                    '%1$s can contain only instances of %3$s, instance of %2$s given',
                    array_shift($shortClasses),
                    array_shift($shortClasses),
                    implode(" or ", $shortClasses)
                ));
            }
        }

        if ($this === $value->getParentNode()) {
            $this->removeChild($value);
        }
        $value->setParentNode($this);

        return $value;
    }
}
