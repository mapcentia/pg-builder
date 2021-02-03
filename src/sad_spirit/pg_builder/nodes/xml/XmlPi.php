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

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    Identifier,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlpi() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read Identifier            $name
 * @property      ScalarExpression|null $content
 */
class XmlPi extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @var Identifier */
    protected $p_name;
    /** @var ScalarExpression|null */
    protected $p_content;

    public function __construct(Identifier $name, ScalarExpression $content = null)
    {
        $this->generatePropertyNames();
        $this->setProperty($this->p_name, $name);
        $this->setProperty($this->p_content, $content);
    }

    public function setContent(ScalarExpression $content): void
    {
        $this->setProperty($this->p_content, $content);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlPi($this);
    }
}
