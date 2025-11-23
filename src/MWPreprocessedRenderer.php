<?php

namespace MediaWiki\Extension\MarkdownPages;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use RuntimeException;

/**
 * A renderer for `MWPreprocessedInline` nodes, where we actually did the
 * processing earlier and now just need to provide the raw HTML without it
 * getting escaped. There does not appear to be an existing renderer for this.
 *
 * @license MIT
 */
class MWPreprocessedRenderer implements NodeRendererInterface {

	/**
	 * Render the node, which must be an instance of `MWPreprocessedInline`.
	 *
	 * @param Node $node
	 * @param ChildNodeRendererInterface $childRenderer
	 * @return string The HTML to render
	 */
	public function render( Node $node, ChildNodeRendererInterface $childRenderer ) {
		if ( !( $node instanceof MWPreprocessedInline ) ) {
			throw new RuntimeException(
				'MWImageRenderer used with ' . get_class( $node )
			);
		}
		return $node->getLiteral();
	}
}
