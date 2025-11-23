<?php

namespace MediaWiki\Extension\MarkdownPages\Tests\Integration;

use MediaWiki\Extension\MarkdownPages\MarkdownContent;
use MediaWiki\MainConfigNames;
use MediaWiki\MainConfigSchema;
use MediaWiki\Page\PageReferenceValue;
use MediaWikiIntegrationTestCase;
use ParserOptions;

/**
 * Tests that the markdown content handler has the same definition of external
 * links as the wikitext parser
 *
 * @covers \MediaWiki\Extension\MarkdownPages\MarkdownContent
 * @covers \MediaWiki\Extension\MarkdownPages\MarkdownContentHandler
 * @group extension-MarkdownPages
 * @group Database
 * @license MIT
 */
class ExternalLinksTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			MainConfigNames::LanguageCode => 'en',
		] );
	}

	public static function provideStandardCases() {
		yield 'http' => [ 'http://wikiteq.com', true ];
		yield 'https' => [ 'https://wikiteq.com', true ];
		yield 'relative' => [ '//wikiteq.com', true ];
		yield 'no protocol or slash' => [ 'wikiteq.com', false ];
		yield 'with parens' => [ 'https://wikiteq.com/foo()bar', true ];

		// Some weird cases
		yield 'with dots' => [
			'https://wikiteq.com/foo/./../bar',
			'https://wikiteq.com/bar'
		];
	}

	/**
	 * @dataProvider provideStandardCases
	 */
	public function testExternalLinks( string $link, bool|string $expected ) {
		if ( $expected === true ) {
			$expectedLinks = [ $link ];
		} elseif ( $expected === false ) {
			$expectedLinks = [];
		} else {
			$expectedLinks = [ $expected ];
		}
		$this->doTest(
			"[$link text]",
			"[text]($link)",
			$expectedLinks
		);
	}

	public function testAllProtocols() {
		// Make sure we run with the defaults so that we have a reasonable
		// set of cases
		$protocols = MainConfigSchema::UrlProtocols['default'];
		$this->overrideConfigValue( MainConfigNames::UrlProtocols, $protocols );

		$wikitextLines = array_map(
			static fn ( $protocol ) => "[{$protocol}wikiteq.com text]",
			$protocols
		);
		$markdownLines = array_map(
			static fn ( $protocol ) => "[text]({$protocol}wikiteq.com)",
			$protocols
		);
		$this->doTest(
			implode( "\n", $wikitextLines ),
			implode( "\n", $markdownLines ),
			array_map(
				static fn ( $protocol ) => $protocol . 'wikiteq.com',
				$protocols
			),
		);
	}

	public function testNonProtocols() {
		// Use the defaults as the allowed protocols
		$protocols = MainConfigSchema::UrlProtocols['default'];
		$this->overrideConfigValue( MainConfigNames::UrlProtocols, $protocols );

		$this->assertNotContains( 'foo://', $protocols );
		$this->assertnotContains( 'bar:', $protocols );

		$nonProtocols = [ 'foo://', 'bar:' ];

		$wikitextLines = array_map(
			static fn ( $protocol ) => "[{$protocol}wikiteq.com text]",
			$nonProtocols
		);
		$markdownLines = array_map(
			static fn ( $protocol ) => "[text]({$protocol}wikiteq.com)",
			$nonProtocols
		);
		$this->doTest(
			implode( "\n", $wikitextLines ),
			implode( "\n", $markdownLines ),
			[]
		);
	}

	public function doTest( string $wikitext, string $markdown, array $expected ) {
		$page = PageReferenceValue::localReference( NS_MAIN, 'Testing' );
		$parserOptions = ParserOptions::newFromAnon();
		$parser = $this->getServiceContainer()->getParser();

		$wikitextOutput = $parser->parse( $wikitext, $page, $parserOptions );
		$wikitextLinks = array_keys( $wikitextOutput->getExternalLinks() );

		$content = new MarkdownContent( $markdown );
		$renderer = $this->getServiceContainer()->getContentRenderer();
		$markdownOutput = $renderer->getParserOutput( $content, $page );
		$markdownLinks = array_keys( $markdownOutput->getExternalLinks() );

		$this->assertSame( $wikitextLinks, $markdownLinks, 'Parsers agree' );
		$this->assertSame( $expected, $markdownLinks, 'Parser is correct' );
	}
}
