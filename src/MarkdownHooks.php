<?php

namespace MediaWiki\Extension\MarkdownPages;

use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use Title;

/**
 * @license MIT
 */
class MarkdownHooks implements ContentHandlerDefaultModelForHook {

	/**
	 * This hook is called to determine the default content model for a new
	 * page - we want to register `.md` pages as markdown by default.
	 *
	 * @param Title $title The title being created
	 * @param string &$model Model name, can be changed since the parameter is
	 *   passed by reference
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		if ( str_ends_with( $title->getText(), '.md' ) ) {
			$model = MarkdownContent::CONTENT_MODEL;
			// Prevent other hooks from changing the content model to something
			// else
			return false;
		}
	}

}
