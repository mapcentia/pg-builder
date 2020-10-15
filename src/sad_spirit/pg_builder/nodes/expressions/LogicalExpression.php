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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    nodes\ScalarExpression,
    exceptions\InvalidArgumentException,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\lists\ExpressionList;

/**
 * AST node representing a group of expressions combined by AND or OR operators
 *
 * @property-read string $operator
 */
class LogicalExpression extends ExpressionList implements ScalarExpression
{
    public const AND = 'and';
    public const OR  = 'or';

    private const PRECEDENCES = [
        self::AND => self::PRECEDENCE_AND,
        self::OR  => self::PRECEDENCE_OR
    ];

    protected $props = [
        'operator' => self::AND
    ];

    public function __construct($terms = null, string $operator = self::AND)
    {
        if (!isset(self::PRECEDENCES[$operator])) {
            throw new InvalidArgumentException("Unknown logical operator '{$operator}'");
        }
        parent::__construct($terms);
        $this->props['operator'] = $operator;
    }

    /**
     * Adds operator property to serialized string produced by GenericNodeList
     * @return string
     */
    public function serialize(): string
    {
        return $this->props['operator'] . '|' . parent::serialize();
    }

    /**
     * Unserializes both type property and offsets
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $pos = strpos($serialized, '|');
        $this->props['operator'] = substr($serialized, 0, $pos);
        parent::unserialize(substr($serialized, $pos + 1));
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkLogicalExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCES[$this->props['operator']];
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
