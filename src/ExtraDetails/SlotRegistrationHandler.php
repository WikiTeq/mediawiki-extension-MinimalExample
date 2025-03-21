<?php

namespace MediaWiki\Extension\MinimalExample\ExtraDetails;

use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;

/**
 * Hooks for registering the slot role for the extra details feature, on its
 * own because the `MediaWikiServices` hook cannot have services injected.
 *
 * @license MIT
 */
class SlotRegistrationHandler implements MediaWikiServicesHook {

	public const EXTRA_DETAILS_ROLE = 'extra-details';

	/**
	 * This hook is called during the setup of the MediaWikiServices service,
	 * and allows manipulating the services that are registered.
	 *
	 * Here, we manipulate the 'SlotRoleRegistry' service to define the extra
	 * role for the extra details.
	 *
	 * @param MediaWikiServices $services
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			static function ( SlotRoleRegistry $registry ) {
				if ( $registry->isDefinedRole( self::EXTRA_DETAILS_ROLE ) ) {
					// Something already registered this slot role - maybe the hook is
					// being called a second time?
					return;
				}
				$registry->defineRoleWithModel(
					self::EXTRA_DETAILS_ROLE,
					CONTENT_MODEL_WIKITEXT,
					[ 'display' => 'none' ]
				);
			}
		);
	}

}
