<?php

namespace MediaWiki\Extension\MinimalExample\Markdown;

use TextContentHandler;

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

}
