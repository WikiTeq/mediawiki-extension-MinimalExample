<?php

namespace MediaWiki\Extension\MarkdownPages;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Handler for MediaWiki categories
 *
 * @license MIT
 */
class MWCategoryParser implements InlineParserInterface {

	private string $legalTitleChars;
	private MWCategoryTracker $categoryTracker;

	public function __construct(
		string $legalTitleChars,
		MWCategoryTracker $categoryTracker
	) {
		$this->legalTitleChars = $legalTitleChars;
		$this->categoryTracker = $categoryTracker;
	}

	public function getMatchDefinition(): InlineParserMatch {
		// See Parser::handleInternalLinks2() (as of 1.43.0)
		return InlineParserMatch::regex(
			'\\[\\[Category:([' . $this->legalTitleChars . ']+)(?:\\|(.+?))?]]'
		);
	}

	public function parse( InlineParserContext $inlineContext ): bool {
		$matches = $inlineContext->getMatches();
		$this->categoryTracker->onCategoryParse(
			$matches[1],
			$matches[2] ?? ''
		);
		$cursor = $inlineContext->getCursor();
		$cursor->advanceBy( $inlineContext->getFullMatchLength() );
		return true;
	}

}
