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
     * even though parser functions are only used for wikitext, other content
     * models (like MassMessage's MassMessageListContent) can include wikitext,
     * so we still need to check the content model, but since the title object
     * is already known, the check is not considered "expensive".
     * 
     * - Since looking up a page content model is an "expensive" operation in
     * the sense that it generally requires a database lookup, we will mark this
     * parser function as "expensive"; the number of expensive parser function
     * calls that can be used on a single page is limited by the configuration
     * variable $wgExpensiveParserFunctionLimit, which defaults to 100.
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
        // Allow retrieving the current page content model without counting it
        // as an expensive operation, because it isn't.
        if ( $pagename === '' ) {
            $title = $parser->getTitle();
        } else {
            // But creating a Title object is going to be expensive; only do so if
            // the limit on expensive operations has not been reached yet.
            if ( !$parser->incrementExpensiveFunctionCount() ) {
                // `false` if the limit has been exceeded, in which case we don't
                // check and just return an empty string
                return '';
            }

            $title = $this->titleFactory->newFromText( $pagename );
            if ( !$title ) {
                // Invalid page name
                return '';
            }
        }

        if ( !$title->exists() ) {
            // Does not exist yet, so no content model
            return '';
        }

        return $title->getContentModel();
    }
}
