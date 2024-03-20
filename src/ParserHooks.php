<?php

namespace MediaWiki\Extension\MinimalExample;

use MediaWiki\Hook\ParserFirstCallInitHook;
use Parser;

class ParserHooks implements ParserFirstCallInitHook {

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
     * @param Parser $parser
     * @param string $pagename
     */
    public function getPageContentModel(
        Parser $parser,
        string $pagename
    ): string {
        return 'PAGECONTENTMODEL: not implemented yet';
    }
}
