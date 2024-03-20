<?php

namespace MediaWiki\Extension\MinimalExample;

use SpecialPage;

class SpecialDoesUserExist extends SpecialPage {

    public function __construct() {
        parent::__construct( 'DoesUserExist' );
    }

    /**
     * @param string|null $subpage
     */
    public function execute( $subpage ) {
        // Basic setup that *all* special pages should do:
        // Set up the page
        $this->setHeaders();
        // Add a summary of the page functionality
        $this->outputHeader();

        // The username can be given either as the `user` request parameter,
        // or as a subpage. The subpage takes precedence
        if ( $subpage !== null && $subpage !== '' ) {
            $this->checkIfUserExists( $subpage );
            return;
        }
        // Fetch a parameter from the web request; getText() will default to an
        // empty string if the request does not include the parameter
        $usernameParam = $this->getRequest()->getText( 'username' );
        if ( $usernameParam !== '' ) {
            $this->checkIfUserExists( $usernameParam );
            return;
        }

        $this->showInputForm();
    }

    /**
     * Display a form to choose a username to check.
     */
    private function showInputForm(): void {
        $this->getOutput()->addWikiTextAsInterface(
            "'''Unimplemented''': showing the input form."
        );
    }

    /**
     * Check if a user with the given name exists and show the answer.
     *
     * @param string $usernameToCheck
     */
    private function checkIfUserExists( string $usernameToCheck ): void {
        $this->getOutput()->addWikiTextAsInterface(
            "'''Unimplemented''': checking if the user `$usernameToCheck` exists."
        );
    }

}
