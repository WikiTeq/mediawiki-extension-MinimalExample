<?php

namespace MediaWiki\Extension\MinimalExample\Markdown;

use Content;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Node\Query;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use League\CommonMark\Util\RegexHelper;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Utils\UrlUtils;
use ParserOutput;
use TextContentHandler;
use TitleFactory;

/**
 * Content handler to add support for markdown.
 */
class MarkdownContentHandler extends TextContentHandler {

    private TitleFactory $titleFactory;
    private UrlUtils $urlUtils;

    /**
     * Like special pages and api modules, content handlers can have
     * dependencies injected.
     * 
     * @param string $modelId
     * @param TitleFactory $titleFactory
     * @param UrlUtils $urlUtils
     */
    public function __construct(
        string $modelId,
        TitleFactory $titleFactory,
        UrlUtils $urlUtils
    ) {
        // The model id should always be 'markdown' since extending this class
        // is not supported
        parent::__construct( MarkdownContent::CONTENT_MODEL );
        $this->titleFactory = $titleFactory;
        $this->urlUtils = $urlUtils;
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
        $env = new Environment( [
            'allow_unsafe_links' => false,
            'html_input' => 'escape',
            'external_link' => [
                'html_class' => 'external',
            ],
        ] );
        $env->addExtension( new CommonMarkCoreExtension() );
        $env->addExtension( new ExternalLinkExtension() );

        $parser = new MarkdownParser( $env );        
        $parsedResult = $parser->parse( $content->getText() );

        $renderer = new HtmlRenderer( $env );
        $parserOutput->setText(
            $renderer->renderDocument( $parsedResult )
        );
        // Make sure we have link styles, etc.
        $parserOutput->addWrapperDivClass( 'mw-parser-output' );

        // Register both local and external links with MediaWiki so that they
        // show up in page metadata and are known to API modules and special
        // pages like [[Special:WhatLinksHere]] and [[Special:LinkSearch]].
        $allLinks = ( new Query() )
            ->where( Query::type( Link::class ) )
            ->findAll( $parsedResult );
        foreach ( $allLinks as $link ) {
            $url = $link->getUrl();
            // Skip unsafe links since the renderer will skip those too; use
            // same implementation as the renderer does, see the
            // ...\Extension\CommonMark\Renderer\Inline\LinkRenderer::render()
            // implementation as of version 2.4.2
            if ( $url === '' || RegexHelper::isLinkPotentiallyUnsafe( $url ) ) {
                continue;
            }
            // Resolve the url so that relative paths work
            $url = $this->urlUtils->removeDotSegments( $url );
            if ( $this->urlUtils->parse( $url ) !== null ) {
                // Valid external link according to MediaWiki
                $parserOutput->addExternalLink( $url );
            } else {
                // Not a valid external link according to MediaWiki, but that
                // might just be because of the protocol - if it is going to be
                // rendered as an external link, based on parse_url() do not
                // register it as an internal link.
                $parsedUrl = parse_url( $url );
                if ( isset( $parsedUrl['host'] ) ) {
                    continue;
                }
                $title = $this->titleFactory->newFromText( $url );
                if ( $title !== null ) {
                    // Only add valid titles as recorded links
                    $parserOutput->addLink( $title );
                }
            } 
        }
    }
}
