<?php

namespace MediaWiki\Extension\MinimalExample\SyntaxHelp;

use FormSpecialPage;
use HTMLForm;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Linker\LinkTargetLookup;
use MediaWikiTitleCodec;
use PermissionsError;
use Wikimedia\Rdbms\ILoadBalancer;

class SpecialConfigureSyntaxHelp extends FormSpecialPage {

    private IContentHandlerFactory $contentHandlerFactory;
    private ILoadBalancer $dbLoadBalancer;
    private LinkTargetLookup $linkTargetLookup;
    private MediaWikiTitleCodec $titleCodec;

    /**
     * @param IContentHandlerFactory $contentHandlerFactory
     * @param ILoadBalancer $dbLoadBalancer
     * @param LinkTargetLookup $linkTargetLookup
     * @param MediaWikiTitleCodec $titleCodec
     *   We only request the `TitleFormatter` service but we also want the
     *   `TitleParser` service - they are currently implemented together
     *   as the `MediaWikiTitleCodec` class so we just type against that instead
     *   of getting multiple services that in reality are the same object
     */
    public function __construct(
        IContentHandlerFactory $contentHandlerFactory,
        ILoadBalancer $dbLoadBalancer,
        LinkTargetLookup $linkTargetLookup,
        MediaWikiTitleCodec $titleCodec
    ) {
        parent::__construct( 'ConfigureSyntaxHelp' );
        $this->contentHandlerFactory = $contentHandlerFactory;
        $this->dbLoadBalancer = $dbLoadBalancer;
        $this->linkTargetLookup = $linkTargetLookup;
        $this->titleCodec = $titleCodec;
    }

    protected function getFormFields() {
        $fields = [];

        $pages = $this->getAllHelpPages();

        // Allow users with the `syntaxhelp-configure` right to view the
        // configuration but not edit it.
        $disabled = !$this->getAuthority()->isAllowed( 'syntaxhelp-configure' );

        foreach ( $pages as $contentModel => $pageName ) {
            // $pageName is false if it was invalid; will be deleted on
            // submission but should treat as missing here
            if ( $pageName === false ) {
                $pageName = '';
            }
            $fields['content-model-' . $contentModel ] = [
                'type' => 'title',
                'default' => $pageName,
                'label-message' => [ 'configuresyntaxhelp-model', $contentModel ],
                'creatable' => true,
                'required' => false,
                // use `readonly` rather than `disabled` so that the fields are
                // more readable
                'readonly' => $disabled,
            ];
        }
        return $fields;
    }

    /**
     * Update the HTMLForm to remove the submission button for users without
     * the `syntaxhelp-configure` permission.
     *
     * @param HTMLForm $form
     */
    protected function alterForm( HTMLForm $form ) {
        if ( !$this->getAuthority()->isAllowed( 'syntaxhelp-configure' ) ) {
            // We just remove the default submission button; a user could still
            // try and submit by modifying the HTML manually, so there will
            // also be a check when the form is submitted, but we shouldn't
            // show the submit button if we know the user is not allowed to
            // use it.
            $form->suppressDefaultSubmit();
        }
    }

    public function onSubmit( array $data ) {
        // Verify that the user can submit, in case were shown a read-only
        // version but messed around with the raw HTML
        if ( !$this->getAuthority()->isAllowed( 'syntaxhelp-configure' ) ) {
            throw new PermissionsError( 'syntaxhelp-configure' );
        }
        
        // We only want to add rows for real content models (valid ones plus
        // any currently in the database)
        $currPages = $this->getAllHelpPages();

        $updates = [];
        $deletions = [];
        $dbw = $this->dbLoadBalancer->getConnection( DB_PRIMARY );

        foreach ( $currPages as $contentModel => $currPageName ) {
            $newPage = $data['content-model-' . $contentModel];
            if ( $newPage === $currPageName ) {
                continue;
            }
            // For the content models where the page changed, we delete the
            // existing rows so that we can do the insertion all at once
            $deletions[] = $contentModel;
            if ( $newPage === '' ) {
                continue;
            }
            $linkId = $this->linkTargetLookup->acquireLinkTargetId(
                $this->titleCodec->parseTitle( $newPage ),
                $dbw
            );
            $updates[] = [
                'mesh_content_model' => $contentModel,
                'mesh_help_page' => $linkId
            ];
        }

        if ( $deletions ) {
            $dbw->delete(
                'me_syntaxhelp',
                [ 'mesh_content_model' => $deletions ],
                __METHOD__
            );
        }
        if ( $updates ) {
            $dbw->insert(
                'me_syntaxhelp',
                $updates,
                __METHOD__
            );
        }

        return true;
    }

    protected function getDisplayFormat() {
        return 'ooui';
    }

    public function onSuccess() {
        $this->getOutput()->addWikiMsg( 'configuresyntaxhelp-saved' );
    }

    private function getAllHelpPages() {
        $pages = [];

        // For each known content model, add a field
        $knownModels = $this->contentHandlerFactory->getContentModels();
        foreach ( $knownModels as $model ) {
            $pages[ $model ] = '';
        }
        $inHelpTable = $this->dbLoadBalancer
            ->getConnection( DB_REPLICA )
            ->newSelectQueryBuilder()
            ->select( '*' )
            ->from( 'me_syntaxhelp' )
            ->caller( __METHOD__ )
            ->fetchResultSet();

        foreach ( $inHelpTable as $row ) {
            $linkTarget = $this->linkTargetLookup->getLinkTargetById(
                $row->mesh_help_page
            );
            if ( $linkTarget === null ) {
                // Somehow an invalid value was included - include it in the
                // output but as `false` so that it can be deleted later but
                // not shown
                $pages[ $row->mesh_content_model ] = false;
                continue;
            }
            $pageName = $this->titleCodec->getPrefixedText( $linkTarget );
            $pages[ $row->mesh_content_model ] = $pageName;
        }
        return $pages;
    }

}
