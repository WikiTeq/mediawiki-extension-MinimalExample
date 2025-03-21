<?php

namespace MediaWiki\Extension\MinimalExample\ExtraDetails;

use ApiBase;
use ApiMain;
use CommentStoreComment;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;
use WikiPage;
use WikitextContent;

/**
 * API endpoint to set the extra-details slot of a page. Note that this does NOT
 * go through the normal edit flow, so various editing-related hooks and checks
 * (like for spam) may not be triggered, and there is no edit conflict handling.
 *
 * @license MIT
 */
class ApiSetExtraDetails extends ApiBase {

	private TitleFactory $titleFactory;
	private WikiPageFactory $wikiPageFactory;

	/**
	 * @param ApiMain $parent
	 * @param string $name
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ApiMain $parent,
		string $name,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $parent, $name );
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/** @inheritDoc */
	public function execute() {
		$this->checkUserRightsAny( 'edit-extra-details' );

		$params = $this->extractRequestParams();

		$newText = $params['text'];
		// Will be null if not provided, or a string (including an empty string)
		// if it was provided. Manually implement the fact that the parameter
		// is required, see comments in getAllowedParams() for why the default
		// implementation for required parameters is not used
		if ( !is_string( $newText ) ) {
			$this->dieWithError(
				[ 'apierror-missingparam', 'text' ],
				'missingparam'
			);
		}

		$title = $this->titleFactory->newFromLinkTarget( $params['title'] );
		$user = $this->getUser();

		// Make sure that the user can edit the page
		$this->checkTitleUserPermissions( $title, 'edit' );

		// TODO namespace validation?

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$currDetails = $this->getCurrentSlotContent( $wikiPage );
		if ( $newText === $currDetails ) {
			$this->getResult()->addValue(
				null,
				$this->getModuleName(),
				[
					'result' => 'Success',
					'nochange' => true,
				]
			);
			return;
		}

		// Need to make some kind of change
		$updater = $wikiPage->newPageUpdater( $user );
		if ( $newText === '' ) {
			$updater->removeSlot( SlotRegistrationHandler::EXTRA_DETAILS_ROLE );
		} else {
			$updater->setContent(
				SlotRegistrationHandler::EXTRA_DETAILS_ROLE,
				new WikitextContent( $newText )
			);
		}
		$result = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment( trim( $params['summary'] ) )
		);

		if ( $result ) {
			$this->getResult()->addValue(
				null,
				$this->getModuleName(),
				[
					'result' => 'Success',
					'revid' => $result->getId(),
				]
			);
			return;
		}
		$this->dieStatus( $updater->getStatus() );
	}

	/**
	 * @param WikiPage $page
	 * @return string Empty string used when there is no content
	 */
	private function getCurrentSlotContent( WikiPage $page ): string {
		$revision = $page->getRevisionRecord();
		if ( !$revision->hasSlot( SlotRegistrationHandler::EXTRA_DETAILS_ROLE ) ) {
			return '';
		}
		$content = $revision->getContent( SlotRegistrationHandler::EXTRA_DETAILS_ROLE );
		if ( !$content instanceof WikitextContent ) {
			$this->dieWithError( [
				'apierror-setextradetails-not-wikitext',
				$content->getModel()
			] );
		}
		return $content->getText();
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => true,
				TitleDef::PARAM_MUST_EXIST => true,
				TitleDef::PARAM_RETURN_OBJECT => true,
			],
			'text' => [
				ParamValidator::PARAM_TYPE => 'string',
				// This parameter is required, but an empty value can be given
				// explicitly to indicate that the slot should be removed. But,
				// there is a bug in the API action=paraminfo and the subsequent
				// usage in the JavaScript on Special:ApiSandbox that means that
				// an empty string fails the validation as a required field,
				// even if the `allowEmptyWhenRequired` option is used here.
				// Instead, make the parameter not required, and manually
				// require that it be present.
				ParamValidator::PARAM_REQUIRED => false,
			],
			'summary' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
