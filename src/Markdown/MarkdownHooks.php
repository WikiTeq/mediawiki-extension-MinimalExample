<?php

namespace MediaWiki\Extension\MinimalExample\Markdown;

use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use Title;

class MarkdownHooks implements ContentHandlerDefaultModelForHook {

    /**
     * This hook is called to determine the default content model for a new
     * page - we want to register `.md` pages as markdown by default.
     *
     * @param Title $title Title in question
     * @param string &$model Model name. Use with CONTENT_MODEL_XXX constants.
     * @return bool|void True or no return value to continue or false to abort
     */
    public function onContentHandlerDefaultModelFor( $title, &$model ) {
        if ( str_ends_with( $title->getText(), '.md' ) ) {
            $model = MarkdownContent::CONTENT_MODEL;
            // Prevent other hooks
            return false;
        }
    }

}
