<?php

namespace MediaWiki\Extension\MinimalExample\Markdown;

use Content;
use League\CommonMark\CommonMarkConverter;
use MediaWiki\Content\Renderer\ContentParseParams;
use ParserOutput;
use TextContentHandler;
use TitleFactory;

/**
 * Content handler to add support for markdown.
 */
class MarkdownContentHandler extends TextContentHandler {

    /**
     * @param string $modelId
     */
    public function __construct( string $modelId ) {
        // The model id should always be 'markdown' since extending this class
        // is not supported
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
     * Identify the content class that is used by this content model.
     *
     * @inheritDoc
     */
    public function getContentClass() {
        return MarkdownContent::class;
    }

    /**
     * Convert the raw markdown into the corresponding parsed HTML.
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
        // Don't do the complicated parsing logic if we are not generating
        // HTML
        if ( !$cpoParams->getGenerateHtml() ) {
            $parserOutput->setText( null );
            return;
        }

        // Use the external `League\CommonMark` library to convert the markdown
        // to HTML, rather than trying to implement that parsing ourselves. We
        // prevent unsafe links, and for raw HTML we escape it the same way that
        // the core parser does for raw HTML in wikitext.
        $converter = new CommonMarkConverter( [
            'allow_unsafe_links' => false,
            'html_input' => 'escape',
        ] );
        $parsed = $converter->convert( $content->getText() );
        $parserOutput->setText( $parsed );
    }
}
