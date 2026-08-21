<?php

namespace MediaWiki\Extension\MinimalExample\Tests\Integration\Markdown;

use MediaWiki\Extension\MinimalExample\Markdown\MarkdownContent;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use Title;

/**
 * Test that rendering markdown content cannot inject wikitext via image
 * URLs (WE-595).
 *
 * @covers \MediaWiki\Extension\MinimalExample\Markdown\MarkdownContentHandler
 * @group extension-MinimalExample
 * @group Database
 * @license MIT
 */
class MarkdownContentHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * Render the given markdown and return the parser output HTML
	 *
	 * @param string $markdown
	 * @return string
	 */
	private function renderMarkdown( string $markdown ): string {
		$title = Title::newFromText( 'TestMarkdownImageInjection.md' );
		if ( $title === null ) {
			$this->fail( 'Failed to create title for test' );
		}
		'@phan-var Title $title';
		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput(
			new MarkdownContent( $markdown ),
			$title,
			null,
			ParserOptions::newFromAnon()
		);
		$text = $parserOutput->getText();
		return is_string( $text ) ? $text : '';
	}

	public function testValidFileImageStillRenders() {
		$html = $this->renderMarkdown(
			'![Some image](Example.png)'
		);
		// Example.png does not exist on the test wiki, so the MediaWiki
		// parser renders a redlink/upload link for it; the important thing
		// is that the file name made it through the [[File:...]] path
		$this->assertStringContainsString( 'Example.png', $html );
	}

	/** @dataProvider provideImageWikitextInjection */
	public function testImageCannotInjectWikitext(
		string $markdown,
		string $injectedWikitext
	) {
		$html = $this->renderMarkdown( $markdown );

		// None of the user-controlled text may break out into rendered HTML,
		// whether as raw markup or after entity decoding
		$decodedHtml = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( [ $html, $decodedHtml ] as $haystack ) {
			$this->assertStringNotContainsString( $injectedWikitext, $haystack );
		}
	}

	public static function provideImageWikitextInjection() {
		yield 'Raw closing brackets' => [
			'![x](https://example.com/a]]b.png)',
			']]b.png'
		];
		yield 'Percent-encoded closing brackets' => [
			'![x](https://example.com/a%5D%5Db.png)',
			']]b'
		];
		yield 'Percent-decoded brackets in relative URL' => [
			'![x](a]]b.png)',
			']]b'
		];
	}
}
