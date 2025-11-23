<?php

namespace MediaWiki\Extension\MarkdownPages\Tests\Integration;

use FilesystemIterator;
use FSFileBackend;
use GlobIterator;
use LocalFile;
use LocalRepo;
use MediaWiki\Extension\MarkdownPages\MarkdownContent;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageReferenceValue;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use ParserOutput;
use Title;
use WikiMap;

/**
 * Parser tests for markdown, cannot use MediaWiki's parser test system because
 * that only supports tests being wikitext
 *
 * @covers \MediaWiki\Extension\MarkdownPages\MarkdownContent
 * @covers \MediaWiki\Extension\MarkdownPages\MarkdownContentHandler
 * @covers \MediaWiki\Extension\MarkdownPages\MWCategoryParser
 * @covers \MediaWiki\Extension\MarkdownPages\MWCategoryTracker
 * @covers \MediaWiki\Extension\MarkdownPages\MWPreprocessedInline
 * @covers \MediaWiki\Extension\MarkdownPages\MWPreprocessedRenderer
 * @group extension-MarkdownPages
 * @group Database
 * @license MIT
 */
class MarkdownParserTest extends MediaWikiIntegrationTestCase {

	public function addDBDataOnce() {
		$repo = new LocalRepo(
			[
				'class' => LocalRepo::class,
				'name' => 'test',
				'backend' => new FSFileBackend( [
					'name' => 'test-backend',
					'wikiId' => WikiMap::getCurrentWikiId(),
					'basePath' => $this->getNewTempDirectory()
				] )
			]
		);
		$title = Title::makeTitle( NS_FILE, 'Example.jpg' );
		$file = new LocalFile( $title, $repo );
		$path = dirname( __FILE__, 3 ) . '/data/Example.jpg';
		$status = $file->upload(
			$path,
			'comment',
			'page text',
			0,
			false,
			false,
			$this->getTestUser()->getUser()
		);
		$this->assertStatusGood( $status );

		$this->getExistingTestPage( 'Exists' );
		$this->getNonexistingTestPage( 'Missing' );
	}

	public static function provideCases() {
		$iterator = new GlobIterator(
			__DIR__ . '/parser/*.txt',
			FilesystemIterator::CURRENT_AS_PATHNAME
		);
		foreach ( $iterator as $path ) {
			yield $path => [ $path ];
		}
	}

	/**
	 * @dataProvider provideCases
	 */
	public function testMarkdownParser( string $filename ) {
		$this->overrideConfigValues( [
			MainConfigNames::LanguageCode => 'en',
			MainConfigNames::EnableUploads => true,
		] );
		$this->assertFileExists( $filename );
		$contents = file_get_contents( $filename );
		$parts = explode( "----------", $contents );
		if ( count( $parts ) === 2 ) {
			[ $markdown, $expectedHtml ] = $parts;
			$expectedMetadata = '';
		} else {
			$this->assertCount( 3, $parts );
			[ $markdown, $expectedHtml, $expectedMetadata ] = $parts;
		}

		$content = new MarkdownContent( $markdown );
		$page = PageReferenceValue::localReference( NS_MAIN, 'Testing.md' );
		$renderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $renderer->getParserOutput(
			$content,
			$page
		);
		$parserOptions = ParserOptions::newFromAnon();
		$html = $parserOutput->runOutputPipeline( $parserOptions )->getContentHolderText();
		$this->assertSame( trim( $expectedHtml ), trim( $html ) );

		$this->assertSame(
			trim( $expectedMetadata ),
			$this->getMetadata( $parserOutput )
		);
	}

	private function getMetadata( ParserOutput $output ): string {
		$lines = [];

		foreach ( $output->getCategoryMap() as $catLink => $sort ) {
			$lines[] = '[category] link=14:' . $catLink . ' sort=\'' . $sort . '\'';
		}
		foreach ( $output->getImages() as $imageLink => $_ ) {
			$lines[] = '[image] link=6:' . $imageLink;
		}
		foreach ( $output->getLinks() as $ns => $arr ) {
			foreach ( $arr as $dbKey => $_ ) {
				$lines[] = "[local] link=$ns:$dbKey";
			}
		}
		foreach ( $output->getExternalLinks() as $link => $_ ) {
			$lines[] = '[external] link=\'' . $link . '\'';
		}
		return implode( "\n", $lines );
	}
}
