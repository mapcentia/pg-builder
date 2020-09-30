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

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\Lexer;
use sad_spirit\pg_builder\Token;

/**
 * Unit test for query lexer
 */
class LexerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Lexer
     */
    protected $lexer;

    public function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    public function testTokenTypes()
    {
        $stream = $this->lexer->tokenize("sElEcT 'select' \"select\", FOO + 1.2, 3 ! <> :foo, $1::integer");

        $stream->expect(Token::TYPE_KEYWORD, 'select');
        $stream->expect(Token::TYPE_STRING, 'select');
        $stream->expect(Token::TYPE_IDENTIFIER, 'select');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_IDENTIFIER, 'foo');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '+');
        $stream->expect(Token::TYPE_FLOAT, '1.2');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_INTEGER, '3');
        $stream->expect(Token::TYPE_OPERATOR, '!');
        $stream->expect(Token::TYPE_INEQUALITY, '<>');
        $stream->expect(Token::TYPE_NAMED_PARAM, 'foo');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_POSITIONAL_PARAM, '1');
        $stream->expect(Token::TYPE_TYPECAST, '::');
        $stream->expect(Token::TYPE_KEYWORD, 'integer');
        $this->assertTrue($stream->isEOF());
    }

    public function testStripComments()
    {
        $stream = $this->lexer->tokenize(<<<QRY
select FOO -- this is a one-line comment
, bar /* this is
a multiline C-style comment */, "bA""z" /*
this is a /* nested C-style */ comment */
as quux -- another comment
QRY
        );
        $stream->expect(Token::TYPE_KEYWORD, 'select');
        $stream->expect(Token::TYPE_IDENTIFIER, 'foo');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_IDENTIFIER, 'bar');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $stream->expect(Token::TYPE_IDENTIFIER, 'bA"z');
        $stream->expect(Token::TYPE_KEYWORD, 'as');
        $stream->expect(Token::TYPE_IDENTIFIER, 'quux');
        $this->assertTrue($stream->isEOF());
    }

    /**
     * @dataProvider getConcatenatedStrings
     */
    public function testConcatenateStringLiterals(string $sql, array $tokens)
    {
        $stream = $this->lexer->tokenize($sql);
        foreach ($tokens as $token) {
            $this->assertEquals($token, $stream->next()->getValue());
        }
    }

    public function getConcatenatedStrings()
    {
        return [
            [
                <<<QRY
'foo'
    'bar' -- a comment
'baz'
QRY
                , ['foobarbaz']
            ],
            [
                <<<QRY
'foo' /*
    a multiline comment
    */
    'bar'-- a comment with no whitespace
'baz'
 
  
   'quux'
QRY
                , ['foo', 'barbazquux']
            ],
            [
                "'foo'\t\f\r'bar'",
                ['foobar']
            ],
            [
                "'foo'--'bar'",
                ['foo']
            ]
        ];
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMulticharacterOperators()
    {
        $stream = $this->lexer->tokenize(<<<QRY
#!/*--
*/=- @+ <=
+* *+--/*
!=-
QRY
        );
        $stream->expect(Token::TYPE_OPERATOR, '#!');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '=');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '-');
        $stream->expect(Token::TYPE_OPERATOR, '@+');
        $stream->expect(Token::TYPE_INEQUALITY, '<=');
        $stream->expect(Token::TYPE_OPERATOR, '+*');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '*');
        $stream->expect(Token::TYPE_SPECIAL_CHAR, '+');
        $stream->expect(Token::TYPE_OPERATOR, '!=-');
    }

    public function testStandardConformingStrings()
    {
        $string = " 'foo\\\\bar' e'foo\\\\bar' ";
        $stream = $this->lexer->tokenize($string);
        $this->assertEquals('foo\\\\bar', $stream->next()->getValue());
        $this->assertEquals('foo\\bar', $stream->next()->getValue());

        $this->lexer = new Lexer(['standard_conforming_strings' => false]);
        $stream2 = $this->lexer->tokenize($string);
        $this->assertEquals('foo\\bar', $stream2->next()->getValue());
        $this->assertEquals('foo\\bar', $stream2->next()->getValue());
    }

    public function testDollarQuotedString()
    {
        $stream = $this->lexer->tokenize(<<<QRY
    $\$a string$$
    \$foo$ another $$ string ' \\ \$foo$
QRY
        );
        $this->assertEquals('a string', $stream->next()->getValue());
        $this->assertEquals(' another $$ string \' \\ ', $stream->next()->getValue());
    }

    public function testUnterminatedCStyleComment()
    {
        $this->expectException('sad_spirit\pg_builder\exceptions\SyntaxException');
        $this->expectExceptionMessage('Unterminated /* comment');
        $this->lexer->tokenize('/* foo');
    }

    public function testUnterminatedQuotedIdentifier()
    {
        $this->expectException('sad_spirit\pg_builder\exceptions\SyntaxException');
        $this->expectExceptionMessage('Unterminated quoted identifier');
        $this->lexer->tokenize('update "foo ');
    }

    public function testZeroLengthQuotedIdentifier()
    {
        $this->expectException('sad_spirit\pg_builder\exceptions\SyntaxException');
        $this->expectExceptionMessage('Zero-length quoted identifier');
        $this->lexer->tokenize('select "" as foo');
    }

    public function testUnterminatedDollarQuotedString()
    {
        $this->expectException('sad_spirit\pg_builder\exceptions\SyntaxException');
        $this->expectExceptionMessage('Unterminated dollar-quoted string');
        $this->lexer->tokenize('select $foo$ blah $$ blah');
    }

    /**
     * @dataProvider getUnterminatedLiterals
     */
    public function testUnterminatedStringLiteral($literal)
    {
        $this->expectException('sad_spirit\pg_builder\exceptions\SyntaxException');
        $this->expectExceptionMessage('Unterminated string literal');
        $this->lexer->tokenize($literal);
    }

    public function getUnterminatedLiterals()
    {
        return [
            ["select 'foo  "],
            [" 'foo \\' '"], // standards_conforming_string is on by default
            [" e'foo "],
            [" e'foo\\'"],
            [" x'1234"]
        ];
    }

    public function testUnexpectedSymbol()
    {
        $this->expectException('sad_spirit\pg_builder\exceptions\SyntaxException');
        $this->expectExceptionMessage('Unexpected \'{\'');
        $this->lexer->tokenize('select foo{bar}');
    }

    public function testNonAsciiIdentifiers()
    {
        $stream = $this->lexer->tokenize('ИмЯ_бЕз_КаВыЧеК "ИмЯ_в_КаВыЧкАх" $ыыы$строка в долларах$ыыы$ :параметр');

        $stream->expect(Token::TYPE_IDENTIFIER, 'ИмЯ_бЕз_КаВыЧеК');
        $stream->expect(Token::TYPE_IDENTIFIER, 'ИмЯ_в_КаВыЧкАх');
        $stream->expect(Token::TYPE_STRING, 'строка в долларах');
        $stream->expect(Token::TYPE_NAMED_PARAM, 'параметр');
        $this->assertTrue($stream->isEOF());
    }

    public function testDowncaseNonAsciiIdentifiers()
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped('The test requires mbstring extension');
        }

        try {
            $oldEncoding = mb_internal_encoding();
            mb_internal_encoding('CP1251');
            $this->lexer = new Lexer(['ascii_only_downcasing' => false]);
            $stream = $this->lexer->tokenize(mb_convert_encoding('ИмЯ_бЕз_КаВыЧеК "ИмЯ_в_КаВыЧкАх"', 'CP1251', 'UTF8'));
            $stream->expect(Token::TYPE_IDENTIFIER, mb_convert_encoding('имя_без_кавычек', 'CP1251', 'UTF8'));
            $stream->expect(Token::TYPE_IDENTIFIER, mb_convert_encoding('ИмЯ_в_КаВыЧкАх', 'CP1251', 'UTF8'));
            $this->assertTrue($stream->isEOF());
        } finally {
            mb_internal_encoding($oldEncoding);
        }
    }
}
