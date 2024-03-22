<?php

namespace MediaWiki\Extension\MinimalExample\Markdown;

use League\CommonMark\Node\Inline\AbstractStringContainer;

/**
 * A data class that indicates that its contents have already been processed
 * by MediaWiki and can be rendered directly without needing to be HTML escaped,
 * since we trust the MediaWiki renderer. We need a subclass to target the
 * renderer in the environment configuration, but this does not have any
 * actual logic in it.
 */
class MWPreprocessedInline extends AbstractStringContainer {
}
