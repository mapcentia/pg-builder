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
    exceptions\InvalidArgumentException,
    exceptions\SyntaxException,
    nodes\GenericNode,
    nodes\QualifiedOperator,
    nodes\ScalarExpression,
    Lexer,
    TreeWalker
};

/**
 * AST node representing a generic operator-like expression
 *
 * This is used for constructs of the form
 *  * <left argument> <operator>
 *  * <operator> <right argument>
 *  * <left argument> <operator> <right argument>
 * that do not have their own nodes. "Operators" here are not just MathOp / all_Op / etc.
 * productions from grammar, but constructs like e.g. 'IS DISTINCT FROM' as well.
 *
 * @property      ScalarExpression|null    $left
 * @property      ScalarExpression|null    $right
 * @property-read string|QualifiedOperator $operator
 */
class OperatorExpression extends GenericNode implements ScalarExpression
{
    private const PRECEDENCES = [
        '='  => self::PRECEDENCE_COMPARISON,
        '<'  => self::PRECEDENCE_COMPARISON,
        '>'  => self::PRECEDENCE_COMPARISON,
        '<=' => self::PRECEDENCE_COMPARISON,
        '>=' => self::PRECEDENCE_COMPARISON,
        '!=' => self::PRECEDENCE_COMPARISON,
        '<>' => self::PRECEDENCE_COMPARISON,

        '+'  => self::PRECEDENCE_ADDITION,
        '-'  => self::PRECEDENCE_ADDITION,

        '*'  => self::PRECEDENCE_MULTIPLICATION,
        '/'  => self::PRECEDENCE_MULTIPLICATION,
        '%'  => self::PRECEDENCE_MULTIPLICATION,

        '^' => self::PRECEDENCE_EXPONENTIATION
    ];

    private const PRECEDENCES_UNARY = [
        '+' => self::PRECEDENCE_UNARY_MINUS,
        '-' => self::PRECEDENCE_UNARY_MINUS
    ];
    
    public function __construct($operator, ScalarExpression $left = null, ScalarExpression $right = null)
    {
        if (!is_string($operator) && !$operator instanceof QualifiedOperator) {
            throw new InvalidArgumentException(sprintf(
                '%s requires either a string or an instance of QualifiedOperator, %s given',
                __CLASS__,
                is_object($operator) ? 'object(' . get_class($operator) . ')' : gettype($operator)
            ));
        } elseif (is_string($operator) && strlen($operator) !== strspn($operator, Lexer::CHARS_OPERATOR)) {
            throw new SyntaxException(sprintf(
                "%s: '%s' does not look like a valid operator string",
                __CLASS__,
                $operator
            ));
        }
        if (null === $left && null === $right) {
            throw new InvalidArgumentException('At least one operand is required for OperatorExpression');
        }
        $this->setNamedProperty('left', $left);
        $this->setNamedProperty('right', $right);
        $this->props['operator'] = $operator;
    }

    public function setLeft(ScalarExpression $left = null): void
    {
        if (null === $left && null === $this->props['right']) {
            throw new InvalidArgumentException('At least one operand is required for OperatorExpression');
        }
        $this->setNamedProperty('left', $left);
    }

    public function setRight(ScalarExpression $right = null): void
    {
        if (null === $right && null === $this->props['left']) {
            throw new InvalidArgumentException('At least one operand is required for OperatorExpression');
        }
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOperatorExpression($this);
    }

    public function getPrecedence(): int
    {
        if (!is_string($this->props['operator']) || !isset(self::PRECEDENCES[$this->props['operator']])) {
            return null === $this->props['right'] ? self::PRECEDENCE_POSTFIX_OP : self::PRECEDENCE_GENERIC_OP;
        } elseif (null === $this->props['left'] && isset(self::PRECEDENCES_UNARY[$this->props['operator']])) {
            return self::PRECEDENCES_UNARY[$this->props['operator']];
        } else {
            return self::PRECEDENCES[$this->props['operator']];
        }
    }

    public function getAssociativity(): string
    {
        if (!is_string($this->props['operator'])) {
            return self::ASSOCIATIVE_LEFT;
        } elseif (null === $this->props['left'] && isset(self::PRECEDENCES_UNARY[$this->props['operator']])) {
            return self::ASSOCIATIVE_RIGHT;
        } elseif (
            !isset(self::PRECEDENCES[$this->props['operator']])
            || self::PRECEDENCE_COMPARISON !== self::PRECEDENCES[$this->props['operator']]
        ) {
            return self::ASSOCIATIVE_LEFT;
        } else {
            return self::ASSOCIATIVE_NONE;
        }
    }
}
