<?php

namespace MediaWiki\Extension\MinimalExample;

use Content;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Query;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use League\CommonMark\Util\RegexHelper;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Utils\UrlUtils;
use ParserOutput;
use TextContentHandler;
use TitleFactory;

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
    public function __construct( string $modelId, TitleFactory $titleFactory, UrlUtils $urlUtils ) {
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

        $env = new Environment( [
            'allow_unsafe_links' => false,
            'html_input' => 'escape',
        ] );
        $env->addExtension( new CommonMarkCoreExtension() );
        $parser = new MarkdownParser( $env );
        
        $parsedResult = $parser->parse( $content->getText() );

        $renderer = new HtmlRenderer( $env );
        $parserOutput->setText(
            $renderer->renderDocument( $parsedResult )
        );

        // Register links
        $allLinks = (new Query())
            ->where(Query::type(Link::class))
            ->findAll($parsedResult);
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
