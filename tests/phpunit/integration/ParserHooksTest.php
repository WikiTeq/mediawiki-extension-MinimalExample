<?php

namespace MediaWiki\Extension\MinimalExample\Tests\Integration;

use DerivativeContext;
use MediaWiki\Extension\MinimalExample\ParserHooks;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use RequestContext;
use Title;

/**
 * @covers \MediaWiki\Extension\MinimalExample\ParserHooks
 * @group extension-MinimalExample
 * @group Database
 */
class ParserHooksTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;

	public function addDBDataOnce() {
		// Force there to be pages that do not exist, exist as wikitext, and
		// exist as plain text (since that can always be used)
		$this->getNonexistingTestPage( 'PageIsMissing' );
		$this->getExistingTestPage( 'PageIsWikitext' );

		// getExistingTestPage() will always create as wikitext, need to convert
		$plainTextPage = $this->getExistingTestPage( 'PageIsPlainText' );
		$status = $this->getServiceContainer()->getContentModelChangeFactory()
			->newContentModelChange(
				$this->mockRegisteredUltimateAuthority(),
				$plainTextPage,
				CONTENT_MODEL_TEXT
			)
			->doContentModelChange(
				RequestContext::getMain(),
				'Test setup',
				false
			);
		$this->assertStatusGood( $status );
	}

	public function testMagicWordRegistered() {
		$parser = $this->getServiceContainer()->getParser();
		$allFunctionHooks = $parser->getFunctionHooks();
		$this->assertContains( 'PAGECONTENTMODEL', $allFunctionHooks );
	}

	/** @dataProvider provideConfigDefaults */
	public function testSpecialPageNote( bool $showNote ) {
		$this->mergeMwGlobalArrayValue(
			'wgDefaultUserOptions',
			[ 'minimalexample-changecontentmodel-pref' => $showNote ]
		);
		$this->setMwGlobals( [
			'wgLanguageCode' => 'qqx',
		] );

		$contentModelChangePage = $this->getServiceContainer()
			->getSpecialPageFactory()
			->getPage( 'ChangeContentModel' );

		// Ensure the user is allowed to access the page and that the context
		// has a page set
		$ctx = new DerivativeContext( $contentModelChangePage->getContext() );
		$ctx->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$ctx->setTitle( $contentModelChangePage->getPageTitle() );
		$contentModelChangePage->setContext( $ctx );

		$contentModelChangePage->execute( '' );

		$output = $contentModelChangePage->getOutput();
		$output->setTitle( $ctx->getTitle() );
		$outputHTML = $output->output( true );

		if ( $showNote ) {
			$this->assertStringContainsString(
				'(minimalexample-changecontentmodel-cache-note)',
				$outputHTML
			);
		} else {
			$this->assertStringNotContainsString(
				'(minimalexample-changecontentmodel-cache-note)',
				$outputHTML
			);
		}
	}

	/** @dataProvider provideConfigDefaults */
	public function testAddedPreference( bool $default ) {
		// set up the default
		$this->mergeMwGlobalArrayValue(
			'wgDefaultUserOptions',
			[ 'minimalexample-changecontentmodel-pref' => $default ]
		);

		$prefFactory = $this->getServiceContainer()->getPreferencesFactory();
		$context = RequestContext::getMain();
		// Context needs to have some title set for preferences
		$context->setTitle(
			Title::makeTitle( NS_SPECIAL, 'Badtitle/Dummy for test' )
		);
		$formDescriptor = $prefFactory->getFormDescriptor(
			$context->getUser(),
			$context
		);
		$this->assertArrayHasKey(
			'minimalexample-changecontentmodel-pref',
			$formDescriptor
		);
		$this->assertSame(
			[
				'type' => 'check',
				'section' => 'editing/editor',
				'label-message' => 'minimalexample-changecontentmodel-pref-label',
				// Default also gets added based on configuration
				'default' => $default,
			],
			$formDescriptor['minimalexample-changecontentmodel-pref']
		);
	}

	public static function provideConfigDefaults() {
		yield 'Default show note' => [ true ];
		yield 'Default hide note' => [ false ];
	}

	/** @dataProvider providePageContentModel */
	public function testPageContentModel(
		string $parserPageName,
		string $pagename,
		string $expectedContentModel
	) {
		// Pages are set up in addDBDataOnce() above
		$hooks = new ParserHooks(
			$this->getServiceContainer()->getTitleFactory(),
			$this->createNoOpMock( UserOptionsLookup::class )
		);
		$parser = $this->getServiceContainer()->getParser();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->setTitle( Title::newFromText( $parserPageName ) );
		$contentModel = $hooks->getPageContentModel( $parser, $pagename );
		$this->assertSame( $expectedContentModel, $contentModel );
	}

	public static function providePageContentModel() {
		// $parserPageName, $pagename, $expectedContentModel
		yield 'Empty page name, page does not exist' => [
			'PageIsMissing',
			'',
			''
		];
		yield 'Empty page name, page is wikitext' => [
			'PageIsWikitext',
			'',
			CONTENT_MODEL_WIKITEXT
		];
		yield 'Empty page name, page is plain text' => [
			'PageIsPlainText',
			'',
			CONTENT_MODEL_TEXT
		];
		yield 'Invalid' => [ 'Example', 'Talk:', '' ];
		yield 'Missing' => [ 'Example', 'PageIsMissing', '' ];
		yield 'Actual check - wikitext' => [
			'Example',
			'PageIsWikitext',
			CONTENT_MODEL_WIKITEXT
		];
		yield 'Actual check - plain text' => [
			'Example',
			'PageIsPlainText',
			CONTENT_MODEL_TEXT
		];
	}
}
