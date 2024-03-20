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

        // We don't care about the subpage at the moment
        // Output a placeholder message until we implement this
        $out = $this->getOutput();
        $out->addHTML(
            "<p>This is some example raw HTML output since <b>this page is not implemented yet</b>.</p>"
        );
    }

}
