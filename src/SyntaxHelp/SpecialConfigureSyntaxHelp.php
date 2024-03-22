<?php

namespace MediaWiki\Extension\MinimalExample\SyntaxHelp;

use FormSpecialPage;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Linker\LinkTargetLookup;
use MediaWikiTitleCodec;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Create a page that allows configuring where users can get help with syntax
 * for different content models. Stores the information in the custom table
 * `me_syntaxhelp` that this extension creates.
 */
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

    /**
     * Get the fields for this form page - we want a single field for each
     * content model.
     */
    protected function getFormFields() {
        $fields = [];

        $pages = $this->getAllHelpPages();

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
            ];
        }
        return $fields;
    }

    /**
     * Handle the submission of the form.
     * 
     * @param array $data The data of the various form fields
     */
    public function onSubmit( array $data ) {
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

    /**
     * Show this page using the OOUI output rather than the default, so that
     * it looks a bit nicer.
     * 
     * @return string
     */
    protected function getDisplayFormat() {
        return 'ooui';
    }

    /**
     * Add a message informing the user that the configuration was saved when
     * the form submission succeeds.
     */
    public function onSuccess() {
        $this->getOutput()->addWikiMsg( 'configuresyntaxhelp-saved' );
    }

    /**
     * Get the details of all help pages. Returns an array with an entry for
     * any content model that is defined (according to the content handler
     * factory) OR that already has an entry in the database for a help page
     * (in case an extension was uninstalled).
     * 
     * The values are either
     * - an empty string (no help page set)
     * - `false` (an invalid help page was set and should be cleared on update)
     * - string (the name of the help page, including namespace)
     *
     * @return array
     */
    private function getAllHelpPages(): array {
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
