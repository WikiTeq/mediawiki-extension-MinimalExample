/**
 * Since this module is written using package files instead of just a list of
 * scripts, it does not need to be wrapped in an IIFE to prevent leakage.
 */

/**
 * Get a promise with the help page for the current page's content model
 */
const getHelpPage = () => {
    const currentModel = mw.config.get( 'wgPageContentModel' );
    const api = new mw.Api();
    return api.get( {
        action: 'getsyntaxhelp',
        contentmodel: currentModel,
        format: 'json',
        formatversion: 2
    } ).then(
        ( response ) => response.getsyntaxhelp[ currentModel ]
    );
};

/**
 * Given a page title to link to for the help page, get an element for the link
 * to that page
 */
const makePageLink = ( pageName ) => {
    const pageTitle = new mw.Title( pageName );

    // jQuery is always available for use in ResourceLoader modules; use it
    // to simplify construction.
    const $link = $( '<a>' )
        .attr( 'href', pageTitle.getUrl() )
        .attr( 'target', '_blank' )
        .text( mw.msg( 'syntaxhelp-help-label') );
    
    // the `mediawiki.jqueryMsg` module lets us use jQuery elements as message
    // parameters so that the overall link can be in parentheses that are
    // localized properly; use parseDom() so that we also get a jQuery element
    // back.
    const $parenLink = mw.message( 'parentheses', $link ).parseDom();
    // Wrap in an overall <span> so that we can target it with styles
    return $( '<span>' )
        .attr( 'id', 'ext-minimalexample-help-link-wrapper' )
        .append( $parenLink );
};

/**
 * Fetch the name of the syntax help page for the current content model; if
 * there is a page, add a link to it.
 */
const maybeAddHelpLink = () => {
    getHelpPage().then( ( result ) => {
        if ( !( result && result.page ) ) {
            // Nothing to do
            return;
        }
        const $link = makePageLink( result.page );
        const $target = $( '#firstHeading' );
        $target.append( $link );
    } );
};

// Run once the module loads
maybeAddHelpLink();
