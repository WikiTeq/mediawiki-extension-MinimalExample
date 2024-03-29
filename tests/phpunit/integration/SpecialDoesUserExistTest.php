<?php

namespace MediaWiki\Extension\MinimalExample\Tests\Integration;

use FauxRequest;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\MinimalExample\SpecialDoesUserExist;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use SpecialPageTestBase;
use User;

/**
 * @covers \MediaWiki\Extension\MinimalExample\SpecialDoesUserExist
 * @group extension-MinimalExample
 * @group Database
 */
class SpecialDoesUserExistTest extends SpecialPageTestBase {
	use MockAuthorityTrait;

	protected function setUp(): void {
		parent::setUp();

		// Set up blocks for the users created in addDBDataOnce() below.
		// Instead of actually inserting blocks to the database, which can be
		// expensive and requires ensuring that no other conflicting blocks
		// exist, we will register a temporary hook that says that `Daniel`
		// is not blocked (and thus not suppressed) but `Baz is an idiot` is
		// blocked and suppressed
		$this->setTemporaryHook(
			'GetUserBlock',
			function ( $user, $ip, &$block ) {
				if ( $user->getName() === 'Daniel' ) {
					$block = null;
				} elseif ( $user->getName() === 'Baz is an idiot' ) {
					$block = $this->createMock( DatabaseBlock::class );
					$block->method( 'getHideName' )->willReturn( true );
				}
			}
		);
	}

	public function addDBDataOnce() {
		// Set up: the user `Daniel` should exist, and the user
		// `Baz is an idiot` should exist but be hidden
		$daniel = User::newFromName( 'Daniel' );
		if ( !$daniel->isRegistered() ) {
			$status = $daniel->addToDatabase();
			$this->assertStatusGood( $status );
		}

		$bazIdiot = User::newFromName( 'Baz is an idiot' );
		if ( !$bazIdiot->isRegistered() ) {
			$status = $bazIdiot->addToDatabase();
			$this->assertStatusGood( $status );
		}
	}

	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new SpecialDoesUserExist(
			$services->getUserFactory(),
			$services->getUserIdentityLookup()
		);
	}

	/** @dataProvider provideCheckUser */
	public function testCheckUserExists(
		string $name,
		bool $canSeeHidden,
		$expectExist,
		bool $expectSuppressed,
		bool $useSubpage
	) {
		// $expectExist is either true/false, or a string with the normalized
		// form
		$viewer = $this->mockRegisteredAuthorityWithPermissions(
			$canSeeHidden ? [ 'hideuser' ] : []
		);
		$request = new FauxRequest();
		if ( $useSubpage ) {
			$subpageVal = $name;
		} else {
			$request->setVal( 'username', $name );
			$subpageVal = null;
		}

		[ $html, $response ] = $this->executeSpecialPage(
			$subpageVal,
			$request,
			'qqx',
			$viewer
		);

		if ( $expectExist === false ) {
			$existNote = "(doesuserexist-does-not-exist: $name)";
		} elseif ( $expectExist === true ) {
			$existNote = "(doesuserexist-exists-exact: $name)";
		} else {
			$existNote = "(doesuserexist-exists-normalized: $expectExist, $name)";
		}
		$this->assertStringContainsString( $existNote, $html );

		$hiddenNote = '(doesuserexist-exists-hidden)';
		if ( $expectSuppressed ) {
			$this->assertStringContainsString( $hiddenNote, $html );
		} else {
			$this->assertStringNotContainsString( $hiddenNote, $html );
		}
	}

	public static function provideCheckUser() {
		// $name, $canSeeHidden, $expectExist, $expectSuppressed, $useSubpage
		yield 'Really missing (see hidden, subpage)' => [ 'A/b', true, false, false, true ];
		yield 'Really missing (see hidden, query)' => [ 'A/b', true, false, false, false ];
		yield 'Really missing (no hidden, subpage)' => [ 'A/b', false, false, false, true ];
		yield 'Really missing (no hidden, query)' => [ 'A/b', false, false, false, false ];

		yield 'Exists (see hidden, subpage)' => [ 'Daniel', true, true, false, true ];
		yield 'Exists (see hidden, query)' => [ 'Daniel', true, true, false, false ];
		yield 'Exists (no hidden, subpage)' => [ 'Daniel', false, true, false, true ];
		yield 'Exists (no hidden, query)' => [ 'Daniel', false, true, false, false ];

		yield 'Normalize (see hidden, subpage)' => [ 'daniel', true, 'Daniel', false, true ];
		yield 'Normalize (see hidden, query)' => [ 'daniel', true, 'Daniel', false, false ];
		yield 'Normalize (no hidden, subpage)' => [ 'daniel', false, 'Daniel', false, true ];
		yield 'Normalize (no hidden, query)' => [ 'daniel', false, 'Daniel', false, false ];

		$hideName = 'Baz is an idiot';
		yield 'Hidden (see hidden, subpage)' => [ $hideName, true, true, true, true ];
		yield 'Hidden (see hidden, query)' => [ $hideName, true, true, true, false ];
		yield 'Hidden (no hidden, subpage)' => [ $hideName, false, false, false, true ];
		yield 'Hidden (no hidden, query)' => [ $hideName, false, false, false, false ];

		$hideNameLc = 'baz is an idiot';
		yield 'Normalize hidden (see hidden, subpage)' => [ $hideNameLc, true, $hideName, true, true ];
		yield 'Normalize hidden (see hidden, query)' => [ $hideNameLc, true, $hideName, true, false ];
		yield 'Normalize hidden (no hidden, subpage)' => [ $hideNameLc, false, false, false, true ];
		yield 'Normalize hidden (no hidden, query)' => [ $hideNameLc, false, false, false, false ];
	}

	/** @dataProvider provideNoSubpage */
	public function testFormOutput( ?string $subpageVal ) {
		[ $html, $response ] = $this->executeSpecialPage(
			$subpageVal,
			null,
			'qqx'
		);

		// The various strings that should be included in the output HTML
		$strings = [
			// special page heading
			'(doesuserexist-summary)',
			// input label
			'(doesuserexist-username-input)',
			// submit button
			'(doesuserexist-perform-check)',
		];
		foreach ( $strings as $string ) {
			$this->assertStringContainsString( $string, $html );
		}
	}

	public static function provideNoSubpage() {
		yield 'No subpage' => [ null ];
		yield 'Empty subpage' => [ '' ];
	}
}
