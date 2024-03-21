<?php

namespace MediaWiki\Extension\MinimalExample;

use Content;
use League\CommonMark\CommonMarkConverter;
use MediaWiki\Content\Renderer\ContentParseParams;
use ParserOutput;
use TextContentHandler;

class MarkdownContentHandler extends TextContentHandler {

    public function __construct() {
        parent::__construct( MarkdownContent::CONTENT_MODEL );
    }

    /**
     * Return an empty instance of markdown content, i.e. with no text.
     *
     * @inheritDoc
     */
    public function makeEmptyContent() {
        return new MarkdownContent( '' );
    }

    /**
     * Identify the class that is used by this content model.
     *
     * @inheritDoc
     */
    public function getContentClass() {
        return MarkdownContent::class;
    }

    /**
     * Set the HTML and add the appropriate styles.
     *
     * @param Content $content
     * @param ContentParseParams $cpoParams
     * @param ParserOutput &$parserOutput The output object to fill (reference).
     */
    protected function fillParserOutput(
        Content $content,
        ContentParseParams $cpoParams,
        ParserOutput &$parserOutput
    ) {
        if ( !$cpoParams->getGenerateHtml() ) {
            $parserOutput->setText( null );
            return;
        }

        $converter = new CommonMarkConverter( [
            'allow_unsafe_links' => false,
            'html_input' => 'strip',
        ] );
        $parsed = $converter->convert( $content->getText() );
        $parserOutput->setText( $parsed );
    }
}
