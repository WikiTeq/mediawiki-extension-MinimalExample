<?php

namespace MediaWiki\Extension\MinimalExample;

use MediaWiki\Hook\ParserFirstCallInitHook;
use Parser;
use TitleFactory;

class ParserHooks implements ParserFirstCallInitHook {

    private TitleFactory $titleFactory;

    /**
     * Like special pages and API modules, hook handlers can have dependencies
     * injected! See T240307 for how this got implemented in 2019 - it was a
     * huge change. Previously, services needed to be retrieved manually.
     * 
     * @param TitleFactory $titleFactory
     */
    public function __construct( TitleFactory $titleFactory ) {
        $this->titleFactory = $titleFactory;
    }

    /**
     * This hook is called when the parser initialises for the first time.
     *
     * @param Parser $parser Parser object being initialised
     * @return bool|void True or no return value to continue or false to abort
     */
    public function onParserFirstCallInit( $parser ) {
        $parser->setFunctionHook(
            'PAGECONTENTMODEL',
            [ $this, 'getPageContentModel' ],
            // We want this to be invoked with `PAGECONTENTMODEL` rather than
            // `#PAGECONTENTMODEL`
            Parser::SFH_NO_HASH
        );
    }

    /**
     * Handler for the magic word `PAGECONTENTMODEL`. The arguments
     * given here are the parser itself, and then any parameters given to
     * the magic word.
     * 
     * - Since this magic word should only be used for one page at a time, we
     * ignore any subsequent parameters.
     * 
     * - If the page name is empty, we will use the page currently being parsed;
     * since parser functions are used for wikitext we can just assume that the
     * content model is wikitext and avoid an expensive database lookup.
     * 
     * - For pages that do not exist, we cannot be sure what their content
     * model is going to be, so return an empty string.
     * 
     * @param Parser $parser
     * @param string $pagename
     */
    public function getPageContentModel(
        Parser $parser,
        string $pagename
    ): string {
        if ( $pagename === '' ) {
            return CONTENT_MODEL_WIKITEXT;
        }

        $title = $this->titleFactory->newFromText( $pagename );
        if ( !$title ) {
            // Invalid page name
            return '';
        }

        if ( !$title->exists() ) {
            // Does not exist yet, so no content model
            return '';
        }

        return $title->getContentModel();
    }
}
