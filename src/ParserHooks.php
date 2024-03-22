<?php

namespace MediaWiki\Extension\MinimalExample;

use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeFormDisplayHook;
use MediaWiki\User\UserOptionsLookup;
use Parser;
use TitleFactory;

class ParserHooks implements
    GetPreferencesHook,
    ParserFirstCallInitHook,
    SpecialPageBeforeFormDisplayHook
{

    private TitleFactory $titleFactory;
    private UserOptionsLookup $userOptionsLookup;

    /**
     * Like special pages and API modules, hook handlers can have dependencies
     * injected! See T240307 for how this got implemented in 2019 - it was a
     * huge change. Previously, services needed to be retrieved manually.
     * 
     * @param TitleFactory $titleFactory
     * @param UserOptionsLookup $userOptionsLookup
     */
    public function __construct(
        TitleFactory $titleFactory,
        UserOptionsLookup $userOptionsLookup
    ) {
        $this->titleFactory = $titleFactory;
        $this->userOptionsLookup = $userOptionsLookup;
    }

    /**
     * This hook is called when the parser initialises for the first time.
     *
     * @param Parser $parser Parser object being initialised
     * @return bool|void True or no return value to continue or false to abort
     */
    public function onParserFirstCallInit( $parser ) {
        $parser->setFunctionHook(
            'PAGECONTENTMODEL',
            [ $this, 'getPageContentModel' ],
            // We want this to be invoked with `PAGECONTENTMODEL` rather than
            // `#PAGECONTENTMODEL`
            Parser::SFH_NO_HASH
        );
    }

    /**
     * This hook is called before the display of a special page that uses a
     * form; Special:ChangeContentModel is one such page, and the one we want
     * to add extra logic to. On that page we add a note at the top that uses
     * of the `PAGECONTENTMODEL` parser function may display outdated results
     * after the content model of the target page changes, but only show that
     * note if the user hasn't disabled it.
     *
     * @param string $name
     * @param HTMLForm $form
     * @return bool|void True or no return value to continue or false to abort
     */
    public function onSpecialPageBeforeFormDisplay( $name, $form ) {
        if ( $name !== 'ChangeContentModel' ) {
            return;
        }
        // Only show the message if the user wants the reminder
        $showNote = $this->userOptionsLookup->getBoolOption(
            $form->getContext()->getUser(),
            'minimalexample-changecontentmodel-pref'
        );
        if ( !$showNote ) {
            return;
        }
        $message = $form->getContext()->msg(
            'minimalexample-changecontentmodel-cache-note'
        );
        $form->addPreHtml( $message->parse() );
    }

    /**
     * This hook is used to register a new user preference - we want to allow
     * users to disable the note about changing the content model leading to
     * outdated page renderings.
     *
     * @param User $user
     * @param array &$defaultPreferences
     */
    public function onGetPreferences( $user, &$defaultPreferences ) {
        // The default value is configured in extension.json
        $defaultPreferences['minimalexample-changecontentmodel-pref'] = [
            'type' => 'check',
            'section' => 'editing/editor',
            'label-message' => 'minimalexample-changecontentmodel-pref-label',
        ];
    }

    /**
     * Handler for the magic word `PAGECONTENTMODEL`. The arguments
     * given here are the parser itself, and then any parameters given to
     * the magic word.
     * 
     * - Since this magic word should only be used for one page at a time, we
     * ignore any subsequent parameters.
     * 
     * - If the page name is empty, we will use the page currently being parsed;
     * since parser functions are used for wikitext we can just assume that the
     * content model is wikitext and avoid an expensive database lookup.
     * 
     * - Since looking up a page content model is an "expensive" operation in
     * the sense that it generally requires a database lookup, we will mark this
     * parser function as "expensive"; the number of expensive parser function
     * calls that can be used on a single page is limited by the configuration
     * variable $wgExpensiveParserFunctionLimit, which defaults to 100.
     * 
     * - For pages that do not exist, we cannot be sure what their content
     * model is going to be, so return an empty string.
     * 
     * @param Parser $parser
     * @param string $pagename
     */
    public function getPageContentModel(
        Parser $parser,
        string $pagename
    ): string {
        // Allow retrieving the current page content model without counting it
        // as an expensive operation, because it isn't.
        if ( $pagename === '' ) {
            return CONTENT_MODEL_WIKITEXT;
        }

        // But creating a Title object is going to be expensive; only do so if
        // the limit on expensive operations has not been reached yet.
        if ( $parser->incrementExpensiveFunctionCount() ) {
            // `true` if the limit has been exceeded, in which case we don't
            // check and just return an empty string
            return '';
        }

        $title = $this->titleFactory->newFromText( $pagename );
        if ( !$title ) {
            // Invalid page name
            return '';
        }

        if ( !$title->exists() ) {
            // Does not exist yet, so no content model
            return '';
        }

        return $title->getContentModel();
    }
}
