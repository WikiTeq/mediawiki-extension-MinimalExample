<?php

namespace MediaWiki\Extension\MinimalExample;

use HTMLForm;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use SpecialPage;

class SpecialDoesUserExist extends SpecialPage {

    private UserFactory $userFactory;
    private UserIdentityLookup $userLookup;

    /**
     * Using dependency injection via ObjectFactory specifications, services
     * can be provided directly to the special page rather than needing to
     * fetch them from MediaWikiServices.
     *
     * @param UserFactory $userFactory
     * @param UserIdentityLookup $userLookup
     */
    public function __construct(
        UserFactory $userFactory,
        UserIdentityLookup $userLookup
    ) {
        parent::__construct( 'DoesUserExist' );
        $this->userFactory = $userFactory;
        $this->userLookup = $userLookup;
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
                'label-message' => 'doesuserexist-username-input',

                // uses HTMLTextField for display
                'type' => 'text',

                // put the value in the `username` request parameter instead
                // of the default `wpName` (since this input field is under
                // the key 'Name').
                'name' => 'username',

                // it does not make sense to submit an empty username
                'required' => true,
            ],
        ];
        // Create the actual form; use OOUI for a nicer output
        $form = HTMLForm::factory( 'ooui', $fields, $this->getContext() );

        // Add a custom label for the submission button
        $form->setSubmitTextMsg( 'doesuserexist-perform-check' );

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
        $userIdentity = $this->userLookup->getUserIdentityByName(
            $usernameToCheck
        );
        if ( $userIdentity === null ) {
            $this->getOutput()->addWikiMsg(
                'doesuserexist-does-not-exist',
                $usernameToCheck
            );
            return;
        }
        // Since the user exists, we also need to check if they are "hidden",
        // meaning that mentions of their username were suppressed on the wiki
        // and the existence should only be revealed if the viewer has special
        // permissions. Otherwise, this would leak private information.
        // This is an easy step to forget, and has affected WMF-deployed
        // extensions repeatedly in the past.

        // There is no way to check if a `UserIdentity` (which is what
        // UserIdentityLookup::getUserIdentityByName() will return if a user
        // exists) is hidden directly; we need a full `User` object.
        $userObj = $this->userFactory->newFromUserIdentity( $userIdentity );
        $hidden = $userObj->isHidden();

        // If the user is hidden, require that the viewer have the `hideuser`
        // permission
        if ( $hidden && !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
            // Pretend like the user does not exist
            $this->getOutput()->addWikiMsg(
                'doesuserexist-does-not-exist',
                $usernameToCheck
            );
            return;
        }

        // Either not hidden or viewer has the ability to see hidden users

        $normalName = $userIdentity->getName();
        $usernameNormalized = $normalName !== $usernameToCheck;
        if ( $usernameNormalized ) {
            $this->getOutput()->addWikiMsg(
                'doesuserexist-exists-normalized',
                $normalName,
                $usernameToCheck
            );
        } else {
            $this->getOutput()->addWikiMsg(
                'doesuserexist-exists-exact',
                $usernameToCheck
            );
        }
        // If the user was hidden, add a note saying so; if we got here and
        // the user is hidden the viewer must be able to see hidden users.
        if ( $hidden ) {
            $this->getOutput()->addWikiMsg(
                'doesuserexist-exists-hidden'
            );
        }
    }

}
