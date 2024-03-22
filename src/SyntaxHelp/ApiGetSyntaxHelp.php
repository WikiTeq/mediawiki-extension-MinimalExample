<?php

namespace MediaWiki\Extension\MinimalExample\SyntaxHelp;

use ApiBase;
use ApiMain;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Linker\LinkTargetLookup;
use MediaWikiTitleCodec;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * API module to retrieve the help page associated with a content model.
 */
class ApiGetSyntaxHelp extends ApiBase {

    private IContentHandlerFactory $contentHandlerFactory;
    private ILoadBalancer $dbLoadBalancer;
    private LinkTargetLookup $linkTargetLookup;
    private MediaWikiTitleCodec $titleCodec;

    /**
     * @param ApiMain $main
     * @param string $action
     * @param IContentHandlerFactory $contentHandlerFactory
     * @param ILoadBalancer $dbLoadBalancer
     * @param LinkTargetLookup $linkTargetLookup
     * @param MediaWikiTitleCodec $titleCodec
     *   We only request the `TitleFormatter` service but we also want the
     *   `TitleParser` service - they are currently implemented together
     *   as the `MediaWikiTitleCodec` class so we just type against that instead
     *   of getting multiple services that in reality are the same object
     */
    public function __construct(
        ApiMain $main,
        string $action,
        IContentHandlerFactory $contentHandlerFactory,
        ILoadBalancer $dbLoadBalancer,
        LinkTargetLookup $linkTargetLookup,
        MediaWikiTitleCodec $titleCodec
    ) {
        parent::__construct( $main, $action );
        $this->contentHandlerFactory = $contentHandlerFactory;
        $this->dbLoadBalancer = $dbLoadBalancer;
        $this->linkTargetLookup = $linkTargetLookup;
        $this->titleCodec = $titleCodec;
    }

    public function execute() {
        // Extract the content model
        $params = $this->extractRequestParams();
        $contentModel = $params['contentmodel'];

        // Split into a separate function to allow for early returns
        $result = $this->getResultForContentModel( $contentModel );

        // Return the information
        $this->getResult()->addValue(
            null,
            $this->getModuleName(),
            [ $contentModel => $result ]
        );
    }

    /**
     * For a given content model, either return an array with the key 'missing'
     * and the value `true` (if there is no page configured or the page
     * configured is invalid) or an array with the key 'page' and the value
     * being the name of the page.
     * 
     * @param string $contentModel
     * @return array
     */
    private function getResultForContentModel( string $contentModel ): array {
        // Do we have a page configured?
        $linkId = $this->dbLoadBalancer
            ->getConnection( DB_REPLICA )
            ->newSelectQueryBuilder()
            ->select( 'mesh_help_page' )
            ->from( 'me_syntaxhelp' )
            ->where( [ 'mesh_content_model' => $contentModel ] )
            ->caller( __METHOD__ )
            ->fetchField();
        if ( $linkId === false ) {
            return [ 'missing' => true ];
        }
        $linkTarget = $this->linkTargetLookup->getLinkTargetById( $linkId );
        if ( $linkTarget === null ) {
            // Database has an invalid value
            return [ 'missing' => true ];
        }
        return [ 'page' => $this->titleCodec->getPrefixedText( $linkTarget ) ];
    }

    public function getAllowedParams() {
        $validModels = $this->contentHandlerFactory->getContentModels();
        return [
            'contentmodel' => [
                // It only makes sense to get the help page for a content model
                // that is currently installed
                ParamValidator::PARAM_TYPE => $validModels,
                // Must be provided
                ParamValidator::PARAM_REQUIRED => true,
            ],
        ];
    }

    protected function getExamplesMessages() {
        return [
            'action=getsyntaxhelp&contentmodel=wikitext'
                => 'apihelp-getsyntaxhelp-example-wikitext',
            'action=getsyntaxhelp&contentmodel=unknown'
                => 'apihelp-getsyntaxhelp-example-unknown',
            'action=getsyntaxhelp&contentmodel=invalid'
                => 'apihelp-getsyntaxhelp-example-invalid',
        ];
    }
}
