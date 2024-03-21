<?php

namespace MediaWiki\Extension\MinimalExample;

use Content;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Node\Query;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use League\CommonMark\Util\RegexHelper;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Utils\UrlUtils;
use Parser;
use ParserFactory;
use ParserOptions;
use ParserOutput;
use TextContentHandler;
use TitleFactory;

class MarkdownContentHandler extends TextContentHandler {

    private ParserFactory $parserFactory;
    private TitleFactory $titleFactory;
    private UrlUtils $urlUtils;

    /**
     * Like special pages and api modules, content handlers can have
     * dependencies injected.
     * 
     * @param string $modelId
     * @param ParserFactory $parserFactory
     * @param TitleFactory $titleFactory
     * @param UrlUtils $urlUtils
     */
    public function __construct(
        string $modelId,
        ParserFactory $parserFactory,
        TitleFactory $titleFactory,
        UrlUtils $urlUtils
    ) {
        parent::__construct( MarkdownContent::CONTENT_MODEL );
        $this->parserFactory = $parserFactory;
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
            'external_link' => [
                'html_class' => 'external',
            ],
        ] );
        $env->addExtension( new CommonMarkCoreExtension() );
        $env->addExtension( new ExternalLinkExtension() );

        // We want a custom image renderer that works for MediaWiki images;
        // but if we just replace the default one then we need to re-implement
        // the logic to display broken images. Instead, let use add a custom
        // node MWPreprocessedInline with its own renderer
        $env->addRenderer(
            MWPreprocessedInline::class,
            new MWPreprocessedRenderer()
        );

        $parser = new MarkdownParser( $env );
        
        $parsedResult = $parser->parse( $content->getText() );

        // Handle images
        $allImages = (new Query())
            ->where(Query::type(Image::class))
            ->findAll($parsedResult);
        $mwParser = $this->parserFactory->getInstance();
        foreach ( $allImages as $image ) {
            $url = $image->getUrl();
            // Ignore external links
            $parsedUrl = parse_url( $url );
            if ( isset( $parsedUrl['host'] ) ) {
                $image->setUrl( '' );
                continue;
            }
            // Otherwise, create the image and replace the default implementation
            $mwParsedImageOut = $mwParser->parse(
                '[[File:' . $url . ']]',
                $cpoParams->getPage(),
                ParserOptions::newFromAnon(),
                true,
                true,
                $cpoParams->getRevId()
            );
            $mwParsedImageOut->clearWrapperDivClass();
            $image->replaceWith(
                new MWPreprocessedInline(
                    Parser::stripOuterParagraph( $mwParsedImageOut->getRawText() )
                )
            );
            // And register that the page uses the image, but don't copy
            // the data about broken images to categories
            $mwParsedImageOut->setCategories( [] );
            $parserOutput->mergeTrackingMetaDataFrom( $mwParsedImageOut );
        }

        $renderer = new HtmlRenderer( $env );
        $parserOutput->setText(
            $renderer->renderDocument( $parsedResult )
        );
        // Make sure we have link styles, etc.
        $parserOutput->addWrapperDivClass( 'mw-parser-output' );

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
