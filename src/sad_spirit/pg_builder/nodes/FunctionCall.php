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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\lists\OrderByList;
use sad_spirit\pg_builder\nodes\lists\FunctionArgumentList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a function call (func_application or func_expr_common_subexpr)
 *
 * This will be wrapped by either expressions\FunctionExpression for scalar
 * contexts where window functions are possible, or by range\FunctionCall
 * for functions in FROM
 *
 * @property-read string|QualifiedName      $name
 * @property      FunctionArgumentList|Star $arguments
 * @property-read bool                      $distinct
 * @property-read bool                      $variadic
 * @property      OrderByList               $order
 */
class FunctionCall extends GenericNode implements FunctionLike
{
    /** @var string|QualifiedName */
    protected $p_name;
    /** @var FunctionArgumentList|Star */
    protected $p_arguments;
    /** @var bool */
    protected $p_distinct;
    /** @var bool */
    protected $p_variadic;
    /** @var OrderByList */
    protected $p_order;

    /**
     * FunctionCall constructor
     *
     * @param string|QualifiedName           $funcName
     * @param FunctionArgumentList|Star|null $arguments
     * @param bool                           $distinct
     * @param bool                           $variadic
     * @param OrderByList|null               $orderBy
     */
    public function __construct(
        $funcName,
        $arguments = null,
        bool $distinct = false,
        bool $variadic = false,
        OrderByList $orderBy = null
    ) {
        if (!is_string($funcName) && !($funcName instanceof QualifiedName)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a string or a QualifiedName as function name, %s given',
                __CLASS__,
                is_object($funcName) ? 'object(' . get_class($funcName) . ')' : gettype($funcName)
            ));
        }
        if (null !== $arguments && !($arguments instanceof FunctionArgumentList) && !($arguments instanceof Star)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of either Star or FunctionArgumentList for function arguments, %s given',
                __CLASS__,
                is_object($arguments) ? 'object(' . get_class($arguments) . ')' : gettype($arguments)
            ));
        }

        $this->generatePropertyNames();
        if ($funcName instanceof QualifiedName) {
            $this->setProperty($this->p_name, $funcName);
        } else {
            $this->p_name = $funcName;
        }
        $this->setProperty($this->p_arguments, $arguments ?? new FunctionArgumentList([]));
        $this->p_distinct = $distinct;
        $this->p_variadic = $variadic;
        $this->setProperty($this->p_order, $orderBy ?? new OrderByList());
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkFunctionCall($this);
    }
}
