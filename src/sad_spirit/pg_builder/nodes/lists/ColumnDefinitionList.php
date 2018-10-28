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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\nodes\range\ColumnDefinition,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Column definition list, may appear in aliases for functions in FROM
 */
class ColumnDefinitionList extends NonAssociativeList
{
    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (!($value instanceof ColumnDefinition)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of ColumnDefinition, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }
}