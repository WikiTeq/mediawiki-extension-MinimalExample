<?php

namespace MediaWiki\Extension\MinimalExample;

use HTMLForm;
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
        // Fields that our form will have
        $fields = [
            'Name' => [
                'label' => 'Username:',

                // uses HTMLTextField for display
                'type' => 'text',

                // put the value in the `username` request parameter instead
                // of the default `wpName` (since this input field is under
                // the key 'Name').
                'name' => 'username',
            ],
        ];
        // Create the actual form; use OOUI for a nicer output
        $form = HTMLForm::factory( 'ooui', $fields, $this->getContext() );

        // Add a custom label for the submission button
        $form->setSubmitText( 'Check if a user exists!' );

        // Use GET submissions so that the requested username is in the URL;
        // this isn't needed but makes things clearer
        $form->setMethod( 'get' );

        // We don't use a normal submission handler since we just want
        // the submission to reload the page with the parameter, but this needs
        // to be called with something
        $form->setSubmitCallback(
            fn () => false
        );

        // Tell the form to show itself; we don't need to manually add it to
        // the page output
        $form->show();
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
