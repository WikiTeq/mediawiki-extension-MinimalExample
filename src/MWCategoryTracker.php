<?php

namespace MediaWiki\Extension\MarkdownPages;

use ParserOutput;

/**
 * Handler for MediaWiki categories
 *
 * @license MIT
 */
class MWCategoryTracker {

	private array $categories;

	public function __construct() {
		$this->categories = [];
	}

	public function onCategoryParse( string $category, string $sort ) {
		$this->categories[$category] = $sort;
	}

	public function exportToMediaWiki( ParserOutput $parserOutput ): void {
		foreach ( $this->categories as $cat => $sort ) {
			$parserOutput->addCategory( $cat, $sort );
		}
	}

}
