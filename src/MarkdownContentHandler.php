<?php

namespace MediaWiki\Extension\MinimalExample;

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
}
