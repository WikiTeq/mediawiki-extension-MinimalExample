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
		yield 'Percent-encoded subpage traversal' => [
			'![x](..%2F..%2Fsecrets.png)',
			'../'
		];
	}

	/** @dataProvider provideNeutralizedImageUrl */
	public function testNeutralizedImageDoesNotLeakUrl(
		string $markdown,
		string $originalUrl
	) {
		$html = $this->renderMarkdown( $markdown );

		// The image was neutralized, so the URL that failed validation may
		// not leak into the output, whether as raw markup or after entity
		// decoding
		$decodedHtml = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( [ $html, $decodedHtml ] as $haystack ) {
			$this->assertStringNotContainsString( $originalUrl, $haystack );
		}
	}

	public static function provideNeutralizedImageUrl() {
		yield 'Raw closing brackets neutralized to empty src' => [
			'![x](https://example.com/a]]b.png)',
			'https://example.com/a]]b.png'
		];
		yield 'Subpage traversal rejected as invalid title' => [
			'![x](../secrets.png)',
			'../secrets.png'
		];
	}

	/** @dataProvider provideSubpageTraversalImageUrl */
	public function testSubpageTraversalStaysWithinFileNamespace(
		string $markdown,
		string $expectedFileTitleText
	) {
		$html = $this->renderMarkdown( $markdown );

		// The traversal is resolved to a File-namespace title (no breakout of
		// that namespace), so the file name must still render through the
		// [[File:...]] path like any other local image
		$this->assertStringContainsString( $expectedFileTitleText, $html );

		// No path segments outside the file name may leak into the output,
		// whether raw or after entity decoding
		$decodedHtml = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( [ $html, $decodedHtml ] as $haystack ) {
			$this->assertStringNotContainsString( '../', $haystack );
			$this->assertStringNotContainsString( '..%2F', $haystack );
		}
	}

	public static function provideSubpageTraversalImageUrl() {
		yield 'Plain relative traversal resolves to valid File title' => [
			'![x](../Example.png)',
			'Example.png'
		];
		yield 'Percent-encoded traversal resolves to valid File title' => [
			'![x](..%2FExample.png)',
			'Example.png'
		];
	}

	public function testFragmentInRelativeUrlIsTruncatedNotRendered() {
		$html = $this->renderMarkdown(
			'![x](Example.png#frag)'
		);

		// MediaWiki title parsing splits off the #fragment and truncates the
		// dbkey at it, so only "Example.png" remains as the file name; the
		// fragment itself may not appear in the output
		$this->assertStringContainsString( 'Example.png', $html );
		$decodedHtml = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( [ $html, $decodedHtml ] as $haystack ) {
			$this->assertStringNotContainsString( '#frag', $haystack );
		}
	}
}
