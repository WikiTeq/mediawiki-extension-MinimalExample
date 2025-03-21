<?php

namespace MediaWiki\Extension\MinimalExample\Tests\Integration\ExtraDetails;

use MediaWiki\Extension\MinimalExample\ExtraDetails\SlotRegistrationHandler;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\MinimalExample\ExtraDetails\SlotRegistrationHandler
 * @group extension-MinimalExample
 * @license MIT
 */
class SlotRegistrationHandlerTest extends MediaWikiIntegrationTestCase {

	public function testRegistered() {
		$slotRoleRegistry = $this->getServiceContainer()->getSlotRoleRegistry();
		$this->assertTrue(
			$slotRoleRegistry->isDefinedRole( SlotRegistrationHandler::EXTRA_DETAILS_ROLE )
		);
	}

}
