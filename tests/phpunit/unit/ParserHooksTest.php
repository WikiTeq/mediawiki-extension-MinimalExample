<?php

namespace MediaWiki\Extension\MinimalExample\Tests\Unit;

use HTMLForm;
use MediaWiki\Extension\MinimalExample\ParserHooks;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use Parser;
use RequestContext;
use Title;
use TitleFactory;
use User;

/**
 * @covers \MediaWiki\Extension\MinimalExample\ParserHooks
 * @group extension-MinimalExample
 * @license MIT
 */
class ParserHooksTest extends MediaWikiUnitTestCase {

	public function testMagicWordRegistered() {
		$hooks = new ParserHooks(
			$this->createNoOpMock( TitleFactory::class ),
			$this->createNoOpMock( UserOptionsLookup::class )
		);
		$parser = $this->createNoOpMock( Parser::class, [ 'setFunctionHook' ] );
		$parser->expects( $this->once() )
			->method( 'setFunctionHook' )
			->with(
				'PAGECONTENTMODEL',
				[ $hooks, 'getPageContentModel' ],
				Parser::SFH_NO_HASH
			);
		$hooks->onParserFirstCallInit( $parser );
	}

	/** @dataProvider provideSpecialPageNote */
	public function testSpecialPageNote(
		string $specialPageName,
		?bool $userOption,
		bool $expectMessage
	) {
		$context = $this->createNoOpMock(
			RequestContext::class,
			[ 'getUser', 'msg' ]
		);
		$optionsLookup = $this->createNoOpMock(
			UserOptionsLookup::class,
			[ 'getBoolOption' ]
		);
		$form = $this->createNoOpMock(
			HTMLForm::class,
			[ 'getContext', 'addPreHtml' ]
		);

		$form->method( 'getContext' )->willReturn( $context );

		if ( $userOption === null ) {
			// Should never use a user
			$context->expects( $this->never() )->method( 'getUser' );
			$optionsLookup->expects( $this->never() )->method( 'getBoolOption' );
		} else {
			// User will be used exactly once
			$user = $this->createNoOpMock( User::class );
			$context->expects( $this->once() )
				->method( 'getUser' )
				->willReturn( $user );
			$optionsLookup->expects( $this->once() )
				->method( 'getBoolOption' )
				->with( $user, 'minimalexample-changecontentmodel-pref' )
				->willReturn( $userOption );
		}

		if ( $expectMessage ) {
			$context->expects( $this->once() )
				->method( 'msg' )
				->with( 'minimalexample-changecontentmodel-cache-note' )
				->WillReturn(
					$this->getMockMessage(
						'minimalexample-changecontentmodel-cache-note CONTENT'
					)
				);
			$form->expects( $this->once() )
				->method( 'addPreHtml' )
				->with( 'minimalexample-changecontentmodel-cache-note CONTENT' );
		} else {
			$form->expects( $this->never() )->method( 'addPreHtml' );
		}

		$hooks = new ParserHooks(
			$this->createNoOpMock( TitleFactory::class ),
			$optionsLookup
		);
		$hooks->onSpecialPageBeforeFormDisplay( $specialPageName, $form );
	}

	public static function provideSpecialPageNote() {
		// $specialPageName, ?bool $userOption, $expectMessage
		yield 'Wrong page' => [ 'Block', null, false ];
		yield 'Opt-out' => [ 'ChangeContentModel', false, false ];
		yield 'Note shown' => [ 'ChangeContentModel', true, true ];
	}

	public function testAddedPreference() {
		$hooks = new ParserHooks(
			$this->createNoOpMock( TitleFactory::class ),
			$this->createNoOpMock( UserOptionsLookup::class )
		);
		$user = $this->createNoOpMock( User::class );
		$defaultPrefs = [];

		$hooks->onGetPreferences( $user, $defaultPrefs );
		$this->assertSame(
			[
				'minimalexample-changecontentmodel-pref' => [
					'type' => 'check',
					'section' => 'editing/editor',
					'label-message' => 'minimalexample-changecontentmodel-pref-label',
				]
			],
			$defaultPrefs
		);
	}

	/** @dataProvider providePageContentModel_samePage */
	public function testPageContentModel_samePage(
		bool $exists,
		?string $model,
		string $expectedContentModel
	) {
		$parser = $this->createNoOpMock(
			Parser::class,
			[ 'getTitle' ]
		);

		$title = $this->createNoOpMock(
			Title::class,
			[ 'exists', 'getContentModel' ]
		);
		$title->expects( $this->once() )
			->method( 'exists' )
			->willReturn( $exists );
		if ( $exists ) {
			$title->expects( $this->once() )
				->method( 'getContentModel' )
				->willReturn( $model );
		} else {
			$title->expects( $this->never() )->method( 'getContentModel' );
		}

		$parser->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( $title );

		$hooks = new ParserHooks(
			$this->createNoOpMock( TitleFactory::class ),
			$this->createNoOpMock( UserOptionsLookup::class )
		);
		$contentModel = $hooks->getPageContentModel( $parser, '' );
		$this->assertSame( $expectedContentModel, $contentModel );
	}

	public static function providePageContentModel_samePage() {
		// $exists, $model, $expectedModel
		yield 'Page does not exist' => [ false, null, '' ];
		yield 'Exists, wikitext' => [
			true,
			CONTENT_MODEL_WIKITEXT,
			CONTENT_MODEL_WIKITEXT,
		];
		yield 'Exists, random' => [ true, 'FooBar', 'FooBar' ];
	}

	/** @dataProvider providePageContentModel_otherPage */
	public function testPageContentModel_otherPage(
		bool $expensiveCount,
		$titleResult,
		string $expectedContentModel
	) {
		$pagename = 'Foo';
		$parser = $this->createNoOpMock(
			Parser::class,
			[ 'incrementExpensiveFunctionCount' ]
		);

		$parser->expects( $this->once() )
			->method( 'incrementExpensiveFunctionCount' )
			->willReturn( $expensiveCount );

		// $titleResult can be `false` for the TitleFactory not being used,
		// `null` for an invalid title, `-1` for a title that does not exist,
		// or a string with a content model
		$titleFactory = $this->createNoOpMock(
			TitleFactory::class,
			[ 'newFromText' ]
		);
		if ( $titleResult === false ) {
			$titleFactory->expects( $this->never() )->method( 'newFromText' );
		} elseif ( $titleResult === null ) {
			$titleFactory->expects( $this->once() )
				->method( 'newFromText' )
				->with( $pagename )
				->willReturn( null );
		} else {
			$title = $this->createNoOpMock(
				Title::class,
				[ 'exists', 'getContentModel' ]
			);
			$title->expects( $this->once() )
				->method( 'exists' )
				->willReturn( $titleResult !== -1 );
			if ( $titleResult === -1 ) {
				$title->expects( $this->never() )->method( 'getContentModel' );
			} else {
				$title->expects( $this->once() )
					->method( 'getContentModel' )
					->willReturn( $titleResult );
			}
			$titleFactory->expects( $this->once() )
				->method( 'newFromText' )
				->with( $pagename )
				->willReturn( $title );
		}

		$hooks = new ParserHooks(
			$titleFactory,
			$this->createNoOpMock( UserOptionsLookup::class )
		);
		$contentModel = $hooks->getPageContentModel( $parser, $pagename );
		$this->assertSame( $expectedContentModel, $contentModel );
	}

	public static function providePageContentModel_otherPage() {
		// $expensiveCount, $titleResult, $expectedContentModel
		yield 'Too expensive' => [ false, false, '' ];
		yield 'Invalid' => [ true, null, '' ];
		yield 'Missing' => [ true, -1, '' ];
		yield 'Actual check' => [ true, 'Bar', 'Bar' ];
	}
}
