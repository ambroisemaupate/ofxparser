<?php

namespace OfxParserTest;

use OfxParser\Parser;

/**
 * @covers OfxParser\Parser
 */
class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testXmlLoadStringThrowsExceptionWithInvalidXml()
    {
        $invalidXml = '<invalid xml>';

        $method = new \ReflectionMethod(Parser::class, 'xmlLoadString');
        $method->setAccessible(true);

        try {
            $method->invoke(new Parser(), $invalidXml);
        } catch (\Exception $e) {
            if (stripos($e->getMessage(), 'Failed to parse OFX') !== false) {
                return true;
            }

            throw $e;
        }

        self::fail('Method xmlLoadString did not raise an expected exception parsing an invalid XML string');
    }

    public function testXmlLoadStringLoadsValidXml()
    {
        $validXml = '<fooRoot><foo>bar</foo></fooRoot>';

        $method = new \ReflectionMethod(Parser::class, 'xmlLoadString');
        $method->setAccessible(true);

        $xml = $method->invoke(new Parser(), $validXml);

        self::assertInstanceOf('SimpleXMLElement', $xml);
        self::assertEquals('bar', (string)$xml->foo);
    }

    /**
     * @return array
     */
    public function testCloseUnclosedXmlTagsProvider()
    {
        return [
            ['<SOMETHING>', '<SOMETHING>'],
            ['<SOMETHING>foo</SOMETHING>', '<SOMETHING>foo'],
            ['<SOMETHING>foo</SOMETHING>', '<SOMETHING>foo</SOMETHING>'],
            ['<BANKID>XXXXX</BANKID>', '<BANKID>XXXXX</BANKID>'],
            ['<ACCTID>XXXXXXXXXXX</ACCTID>', '<ACCTID>XXXXXXXXXXX</ACCTID>'],
            ['<ACCTID>-198.98</ACCTID>', '<ACCTID>-198.98</ACCTID>'],
            ['<ACCTID>-198.98</ACCTID>', '<ACCTID>-198.98'],
        ];
    }

    /**
     * @dataProvider testCloseUnclosedXmlTagsProvider
     * @param $expected
     * @param $input
     */
    public function testCloseUnclosedXmlTags($expected, $input)
    {
        $method = new \ReflectionMethod(Parser::class, 'closeUnclosedXmlTags');
        $method->setAccessible(true);

        $parser = new Parser();

        self::assertEquals($expected, $method->invoke($parser, $input));
    }

    public function testConvertSgmlToXmlProvider()
    {
        return [
            [<<<HERE
<SOMETHING>
    <FOO>bar
    <BAZ>bat</BAZ>
</SOMETHING>
HERE
        , <<<HERE
<SOMETHING>
<FOO>bar</FOO>
<BAZ>bat</BAZ>
</SOMETHING>
HERE
            ], [<<<HERE
<BANKACCTFROM>
<BANKID>XXXXX</BANKID>
<BRANCHID>XXXXX</BRANCHID>
<ACCTID>XXXXXXXXXXX</ACCTID>
<ACCTTYPE>CHECKING</ACCTTYPE>
</BANKACCTFROM>
HERE
                ,<<<HERE
<BANKACCTFROM>
<BANKID>XXXXX</BANKID>
<BRANCHID>XXXXX</BRANCHID>
<ACCTID>XXXXXXXXXXX</ACCTID>
<ACCTTYPE>CHECKING</ACCTTYPE>
</BANKACCTFROM>
HERE
            ],
        ];
    }

    /**
     * @dataProvider testConvertSgmlToXmlProvider
     */
    public function testConvertSgmlToXml($sgml, $expected)
    {
        $method = new \ReflectionMethod(Parser::class, 'convertSgmlToXml');
        $method->setAccessible(true);

        self::assertEquals($expected, $method->invoke(new Parser, $sgml));
    }

    public function testLoadFromFileWhenFileDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);

        $parser = new Parser();
        $parser->loadFromFile('a non-existent file');
    }

    /**
     * @dataProvider testLoadFromStringProvider
     */
    public function testLoadFromFileWhenFileDoesExist($filename)
    {
        if (!file_exists($filename)) {
            self::markTestSkipped('Could not find data file, cannot test loadFromFile method fully');
        }

        /** @var Parser|\PHPUnit_Framework_MockObject_MockObject $parser */
        $parser = $this->getMockBuilder(Parser::class)
                         ->setMethods(['loadFromString'])
                         ->getMock();
        $parser->expects(self::once())->method('loadFromString');
        $parser->loadFromFile($filename);
    }

    /**
     * @return array
     */
    public function testLoadFromStringProvider()
    {
        return [
            [dirname(__DIR__).'/fixtures/ofxdata.ofx'],
            [dirname(__DIR__).'/fixtures/ofxdata-oneline.ofx'],
            [dirname(__DIR__).'/fixtures/ofxdata-cmfr.ofx'],
        ];
    }

    /**
     * @param $filename
     * @throws \Exception
     * @dataProvider testLoadFromStringProvider
     */
    public function testLoadFromString($filename)
    {
        if (!file_exists($filename)) {
            self::markTestSkipped('Could not find data file, cannot test loadFromString method fully');
        }

        $content = file_get_contents($filename);

        $parser = new Parser();
        $parser->loadFromString($content);
    }
}
