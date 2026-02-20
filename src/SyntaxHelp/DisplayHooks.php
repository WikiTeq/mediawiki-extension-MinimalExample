<?php

namespace MediaWiki\Extension\MinimalExample\SyntaxHelp;

use MediaWiki\Hook\BeforePageDisplayHook;
use OutputPage;
use Skin;

class DisplayHooks implements BeforePageDisplayHook {

	/**
	 * This hook is called prior to outputting a page - we use it to add our
	 * ResourceLoader module with a help link for the syntax of the page content
	 * model, but only when the current page could exist as a wikipage and the
	 * page is being edited (the action is either 'edit' or 'submit', we do
	 * not at the moment recognize custom edit actions from other extensions).
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return void This hook cannot be aborted
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$out->getTitle()->canExist() ) {
			return;
		}
		$action = $out->getActionName();
		if ( $action === 'edit' || $action === 'submit' ) {
			$out->addModules( [ 'ext.minimalexample.syntaxhelp' ] );
		}
	}
}
