<?php

namespace MediaWiki\Extension\MinimalExample\Tests\Unit\ExtraDetails;

use MediaWiki\Extension\MinimalExample\ExtraDetails\SlotRegistrationHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MinimalExample\ExtraDetails\SlotRegistrationHandler
 * @group extension-MinimalExample
 * @license MIT
 */
class SlotRegistrationHandlerTest extends MediaWikiUnitTestCase {

	private function runTestCase( $mockSlotRoleRegistry ) {
		$mwServices = $this->createNoOpMock(
			MediaWikiServices::class,
			[ 'addServiceManipulator' ]
		);
		$mwServices->expects( $this->once() )
			->method( 'addServiceManipulator' )
			->with(
				'SlotRoleRegistry',
				$this->callback( function ( $manipulator ) use ( $mockSlotRoleRegistry ) {
					// Run the callback with the mock slot role registry
					$this->assertIsCallable( $manipulator );
					$manipulator( $mockSlotRoleRegistry );
					return true;
				} )
			);
		$hooks = new SlotRegistrationHandler();
		$hooks->onMediaWikiServices( $mwServices );
	}

	public function testAlreadyRegistered() {
		$slotRoleRegistry = $this->createNoOpMock(
			SlotRoleRegistry::class,
			[ 'isDefinedRole' ]
		);
		$slotRoleRegistry->expects( $this->once() )
			->method( 'isDefinedRole' )
			->with( SlotRegistrationHandler::EXTRA_DETAILS_ROLE )
			->willReturn( true );
		$this->runTestCase( $slotRoleRegistry );
	}

	public function testGetsRegistered() {
		$slotRoleRegistry = $this->createNoOpMock(
			SlotRoleRegistry::class,
			[ 'isDefinedRole', 'defineRoleWithModel' ]
		);
		$slotRoleRegistry->expects( $this->once() )
			->method( 'isDefinedRole' )
			->with( SlotRegistrationHandler::EXTRA_DETAILS_ROLE )
			->willReturn( false );
		$slotRoleRegistry->expects( $this->once() )
			->method( 'defineRoleWithModel' )
			->with(
				SlotRegistrationHandler::EXTRA_DETAILS_ROLE,
				CONTENT_MODEL_WIKITEXT,
				[ 'display' => 'none' ]
			);
		$this->runTestCase( $slotRoleRegistry );
	}

}
