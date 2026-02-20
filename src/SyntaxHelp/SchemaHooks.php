<?php

namespace MediaWiki\Extension\MinimalExample\SyntaxHelp;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * This hook is called during database installation and updates - we use
	 * it to register our `me_syntaxhelp` table.
	 *
	 * @param DatabaseUpdater $updater DatabaseUpdater subclass
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = __DIR__ . '/../../schema';
		$maintenanceDb = $updater->getDB();
		$dbType = $maintenanceDb->getType();
		$updater->addExtensionTable(
			'me_syntaxhelp',
			"$base/$dbType/tables-generated.sql"
		);
	}
}
