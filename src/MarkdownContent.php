<?php

namespace MediaWiki\Extension\MarkdownPages;

use TextContent;

/**
 * Content that should be rendered according to markdown parsing.
 *
 * @license MIT
 */
class MarkdownContent extends TextContent {

	public const CONTENT_MODEL = 'markdown';

	/**
	 * @param string $text The markdown
	 * @param string $modelId The id of the content model, in case there is
	 *   a subclass that overrides this
	 */
	public function __construct( $text, $modelId = self::CONTENT_MODEL ) {
		parent::__construct( $text, $modelId );
	}

}
