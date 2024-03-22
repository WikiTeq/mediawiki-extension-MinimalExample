<?php

namespace MediaWiki\Extension\MinimalExample\SyntaxHelp;

use MediaWiki\Hook\BeforePageDisplayHook;
use OutputPage;
use Skin;

class DisplayHooks implements BeforePageDisplayHook {

    /**
     * This hook is called prior to outputting a page - we use it to add our
     * ResourceLoader module with a help link to pages that could exist and
     * the action is 'edit' (or 'submit' which is a subclass in core)
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
