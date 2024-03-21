<?php

namespace MediaWiki\Extension\MinimalExample\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use RuntimeError;

class MWPreprocessedRenderer implements NodeRendererInterface {
    public function render( Node $node, ChildNodeRendererInterface $childRenderer ) {
        if ( !( $node instanceof MWPreprocessedInline ) ) {
            throw new RuntimeError(
                'MWImageRenderer used with ' . get_class( $node )
            );
        }
        return $node->getLiteral();
    }
}
