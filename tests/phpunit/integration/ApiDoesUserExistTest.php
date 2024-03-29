<?php

namespace MediaWiki\Extension\MinimalExample\Tests\Integration;

use ApiTestCase;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use User;

/**
 * @covers \MediaWiki\Extension\MinimalExample\ApiDoesUserExist
 * @group extension-MinimalExample
 * @group Database
 */
class ApiDoesUserExistTest extends ApiTestCase {
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

	/** @dataProvider provideCheckUser */
	public function testCheckUserExists(
		string $name,
		bool $canSeeHidden,
		$expectExist,
		bool $expectHidden
	) {
		// $expectExist is either true/false, or a string with the normalized
		// form
		$requestor = $this->mockRegisteredAuthorityWithPermissions(
			$canSeeHidden ? [ 'hideuser' ] : []
		);

		$result = $this->doApiRequest(
			[
				'action' => 'doesuserexist',
				'username' => $name,
			],
			null,
			false,
			$requestor
		)[0];

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'warnings', $result, 'No warnings' );
		$this->assertArrayHasKey( 'doesuserexist', $result, 'Api module result' );

		$checkResult = $result['doesuserexist'];
		$this->assertIsArray( $checkResult );
		$this->assertCount( 1, $checkResult, 'Only 1 username checked' );
		$userResult = $checkResult[0];

		$expected = [
			'name' => $name,
			'exists' => (bool)$expectExist
		];
		if ( $expectExist !== false ) {
			$expected['hidden'] = $expectHidden;
		}
		if ( is_string( $expectExist ) ) {
			$expected['normalized'] = $expectExist;
		}
		$this->assertSame( $expected, $userResult );
	}

	public static function provideCheckUser() {
		// $name, $canSeeHidden, $expectExist, $expectHidden
		yield 'Really missing (see hidden)' => [ 'A/b', true, false, false ];
		yield 'Really missing (no hidden)' => [ 'A/b', false, false, false ];

		yield 'Exists (see hidden)' => [ 'Daniel', true, true, false ];
		yield 'Exists (no hidden)' => [ 'Daniel', false, true, false ];

		yield 'Normalize (see hidden)' => [ 'daniel', true, 'Daniel', false ];
		yield 'Normalize (no hidden)' => [ 'daniel', false, 'Daniel', false ];

		$hideName = 'Baz is an idiot';
		yield 'Hidden (see hidden)' => [ $hideName, true, true, true ];
		yield 'Hidden (no hidden)' => [ $hideName, false, false, false ];

		$hideNameLc = 'baz is an idiot';
		yield 'Normalize hidden (see hidden)' => [ $hideNameLc, true, $hideName, true ];
		yield 'Normalize hidden (no hidden)' => [ $hideNameLc, false, false, false ];
	}

	public function testRequestMultiple() {
		$result = $this->doApiRequest(
			[
				'action' => 'doesuserexist',
				'username' => 'Foo|Bar|A/b|Daniel|daniel|Baz is an idiot',
			],
			null,
			false,
			$this->mockRegisteredUltimateAuthority()
		)[0];

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'warnings', $result, 'No warnings' );
		$this->assertArrayHasKey( 'doesuserexist', $result, 'Api module result' );

		$checkResult = $result['doesuserexist'];
		$this->assertIsArray( $checkResult );

		$expected = [
			[ 'name' => 'Foo', 'exists' => false ],
			[ 'name' => 'Bar', 'exists' => false ],
			[ 'name' => 'A/b', 'exists' => false ],
			[ 'name' => 'Daniel', 'exists' => true, 'hidden' => false ],
			[ 'name' => 'daniel', 'exists' => true, 'hidden' => false, 'normalized' => 'Daniel' ],
			[ 'name' => 'Baz is an idiot', 'exists' => true, 'hidden' => true ],
		];
		$this->assertSame( $expected, $checkResult );
	}

	public function testDuplicates() {
		$result = $this->doApiRequest(
			[
				'action' => 'doesuserexist',
				'username' => 'Foo|Foo|Foo|Bar|Bar',
			]
		)[0];

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'doesuserexist', $result, 'Api module result' );

		$checkResult = $result['doesuserexist'];
		$this->assertIsArray( $checkResult );
		$this->assertCount( 2, $checkResult, '2 usernames checked' );
		$this->assertSame(
			[
				[ 'name' => 'Foo', 'exists' => false ],
				[ 'name' => 'Bar', 'exists' => false ],
			],
			$checkResult
		);

		$this->assertArrayHasKey( 'warnings', $result, 'Api warnings' );
		$allWarnings = $result['warnings'];

		$this->assertIsArray( $allWarnings );
		$this->assertCount( 1, $allWarnings, 'Only 1 module with warnings' );
		$this->assertArrayHasKey( 'doesuserexist', $allWarnings, 'Module warnings' );

		$warnings = $allWarnings['doesuserexist'];
		$this->assertSame(
			[ 'warnings' => "Duplicate request to check the name: Foo\nDuplicate request to check the name: Bar" ],
			$warnings
		);
	}
}
