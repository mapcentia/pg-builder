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
    Node,
    nodes\WindowDefinition,
    Parseable,
    ElementParseable,
    Parser
};

/**
 * List of window definitions (WINDOW clause of SELECT)
 *
 * @extends NonAssociativeList<WindowDefinition>
 * @implements ElementParseable<WindowDefinition>
 */
class WindowList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [WindowDefinition::class];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseWindowDefinition($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseWindowList($sql);
    }
}
