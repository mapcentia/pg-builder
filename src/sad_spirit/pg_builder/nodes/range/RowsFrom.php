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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\nodes\lists\RowsFromList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a ROWS FROM() construct in FROM clause (PostgreSQL 9.4+)
 *
 * @property RowsFromList $function
 */
class RowsFrom extends FunctionCall
{
    /** @var RowsFromList */
    protected $p_function;

    public function __construct(RowsFromList $function)
    {
        $this->generatePropertyNames();

        $this->p_function = $function;
        $this->p_function->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRowsFrom($this);
    }
}
