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
    nodes\GenericNode,
    nodes\ScalarExpression,
    exceptions\InvalidArgumentException,
    TreeWalker
};

/**
 * AST node representing [NOT] BETWEEN expression
 *
 * @property ScalarExpression $argument
 * @property ScalarExpression $left
 * @property ScalarExpression $right
 * @property string           $operator either of 'between' / 'between symmetric' / 'between asymmetric'
 * @property bool             $negated  set to true for NOT BETWEEN expressions
 */
class BetweenExpression extends GenericNode implements ScalarExpression
{
    public const BETWEEN                = 'between';
    public const BETWEEN_SYMMETRIC      = 'between symmetric';
    public const BETWEEN_ASYMMETRIC     = 'between asymmetric';

    private const ALLOWED_OPERATORS = [
        self::BETWEEN                => true,
        self::BETWEEN_SYMMETRIC      => true,
        self::BETWEEN_ASYMMETRIC     => true
    ];

    /** @var ScalarExpression */
    protected $p_argument;
    /** @var ScalarExpression */
    protected $p_left;
    /** @var ScalarExpression */
    protected $p_right;
    /** @var string */
    protected $p_operator;
    /** @var bool */
    protected $p_negated;

    public function __construct(
        ScalarExpression $argument,
        ScalarExpression $left,
        ScalarExpression $right,
        string $operator = self::BETWEEN,
        bool $negated = false
    ) {
        $this->generatePropertyNames();
        $this->setProperty($this->p_argument, $argument);
        $this->setProperty($this->p_left, $left);
        $this->setProperty($this->p_right, $right);
        $this->setOperator($operator);
        $this->setNegated($negated);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setProperty($this->p_argument, $argument);
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setProperty($this->p_left, $left);
    }

    public function setRight(ScalarExpression $right): void
    {
        $this->setProperty($this->p_right, $right);
    }

    public function setOperator(string $operator): void
    {
        if (!isset(self::ALLOWED_OPERATORS[$operator])) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for BETWEEN-style expression");
        }
        $this->p_operator = $operator;
    }

    public function setNegated(bool $negated): void
    {
        $this->p_negated = $negated;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkBetweenExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_BETWEEN;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
