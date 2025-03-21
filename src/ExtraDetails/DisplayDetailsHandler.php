<?php

namespace MediaWiki\Extension\MinimalExample\ExtraDetails;

use Html;
use MediaWiki\Hook\BeforePageDisplayHook;
use OutputPage;
use Parser;
use Skin;
use WikitextContent;

/**
 * Hook for showing extra details
 *
 * @license MIT
 */
class DisplayDetailsHandler implements BeforePageDisplayHook {

	private Parser $parser;

	/**
	 * @param Parser $parser
	 */
	public function __construct( Parser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * This hook is called before page display and is used to render the extra
	 * details.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		// Only show logged in users, unless they have a query parameter
		if ( $out->getUser()->isAnon() ) {
			$req = $out->getRequest();
			if ( !$req->getCheck( 'force-show-extra-details' ) ) {
				return;
			}
		}
		// Check for a wikipage with the slot
		if ( !$out->canUseWikiPage() ) {
			return;
		}
		$page = $out->getWikiPage();
		$revision = $page->getRevisionRecord();
		if ( !$revision->hasSlot( SlotRegistrationHandler::EXTRA_DETAILS_ROLE ) ) {
			return;
		}
		$content = $revision->getContent( SlotRegistrationHandler::EXTRA_DETAILS_ROLE );
		if ( !$content instanceof WikiTextContent ) {
			$out->prependHTML(
				Html::errorBox(
					$out->msg( 'extradetails-render-not-wikitext' )
						->params( $content->getModel() )
						->parse()
				)
			);
			return;
		}

		$parserOutput = $this->parser->parse(
			$content->getText(),
			$page,
			$out->parserOptions(),
			true,
			true,
			$revision->getId()
		);

		$out->addHTML(
			Html::rawElement(
				'div',
				[ 'class' => 'ext-minimalexample-extra-details' ],
				$parserOutput->getRawText()
			)
		);
		$out->addModuleStyles( 'ext.minimalexample.extradetails' );
	}

}
