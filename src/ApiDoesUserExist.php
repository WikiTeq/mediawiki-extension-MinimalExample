<?php

namespace MediaWiki\Extension\MinimalExample;

use ApiBase;
use ApiMain;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @license MIT
 */
class ApiDoesUserExist extends ApiBase {

	private UserFactory $userFactory;
	private UserIdentityLookup $userLookup;

	/**
	 * Using dependency injection via ObjectFactory specifications, services
	 * can be provided directly to the api module rather than needing to fetch
	 * them from MediaWikiServices. The `ApiMain` instance and the `action` are
	 * always passed when api modules are created but don't need to be used
	 * here, they just get passed to the parent constructor.
	 *
	 * @param ApiMain $main
	 * @param string $action
	 * @param UserFactory $userFactory
	 * @param UserIdentityLookup $userLookup
	 */
	public function __construct(
		ApiMain $main,
		string $action,
		UserFactory $userFactory,
		UserIdentityLookup $userLookup
	) {
		parent::__construct( $main, $action );
		$this->userFactory = $userFactory;
		$this->userLookup = $userLookup;
	}

	public function execute() {
		// Extract the parameters
		$params = $this->extractRequestParams();
		// Get the specific `username` parameter, which must be set; since
		// the parameter accepts multiple values, the result here will always
		// be an array, even if there is just one requested name
		$usernamesToCheck = $params['username'];

		// Look through and check them each, but only check a name once, if it
		// was requested again add a warning. We compare names that are checked
		// based on the *raw* requested username, not the normalized form, so
		// that we don't warn users if they are checking different names that
		// normalize to be the same.
		$checked = [];

		$results = [];
		foreach ( $usernamesToCheck as $name ) {
			if ( isset( $checked[ $name ] ) ) {
				$this->addWarning( [
					'apiwarn-doesuserexist-duplicate-request',
					$name
				] );
				continue;
			}
			$checked[ $name ] = true;
			$results[] = $this->checkIfUserExists( $name );
		}

		// Return the information
		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$results
		);
	}

	/**
	 * Check if the user with the given name exists, taking into account if
	 * the requester is able to view hidden users. Returns an associative
	 * array with the following keys:
	 *  - name: string (the originally requested username without normalization)
	 *  - exists: boolean
	 *  - normalized: string (only set if the user exists and the
	 *    requested name is different from the normalized name)
	 *  - hidden: boolean (only set if the user exists)
	 *
	 * @param string $usernameToCheck
	 * @return array
	 */
	private function checkIfUserExists( string $usernameToCheck ): array {
		$userIdentity = $this->userLookup->getUserIdentityByName(
			$usernameToCheck
		);
		if ( $userIdentity === null ) {
			// Does not exist
			return [ 'name' => $usernameToCheck, 'exists' => false ];
		}

		// Check if the user is hidden and the requester is unable to see hidden
		// users, in which case we pretend that the user doesn't exist at all
		$userObj = $this->userFactory->newFromUserIdentity( $userIdentity );
		$hidden = $userObj->isHidden();
		if ( $hidden && !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
			return [ 'name' => $usernameToCheck, 'exists' => false ];
		}

		// Either the requester is allowed to see hidden users, or the user
		// is not hidden; either way it is okay to report that
		$result = [
			'name' => $usernameToCheck,
			'exists' => true,
			'hidden' => $hidden,
		];

		// Only set the normalized name if it is different from the requested
		// name
		$normalName = $userIdentity->getName();
		if ( $normalName !== $usernameToCheck ) {
			$result['normalized'] = $normalName;
		}
		return $result;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'username' => [
				// We do *not* want to use the MediaWiki 'user' parameter type
				// because that will do a bunch of validation about invalid
				// user names that we want to be demonstrating
				ParamValidator::PARAM_TYPE => 'string',
				// Must be provided, like the special page
				ParamValidator::PARAM_REQUIRED => true,
				// Support checking the existence of multiple users at a time
				ParamValidator::PARAM_ISMULTI => true,
				// Instead of silently ignoring duplicate names, we want to
				// add a warning
				ParamValidator::PARAM_ALLOW_DUPLICATES => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=doesuserexist&username=MediaWiki default'
				=> 'apihelp-doesuserexist-example-mwdefault-exists',
			'action=doesuserexist&username=mediaWiki default'
				=> 'apihelp-doesuserexist-example-mwdefault-normalized',
			'action=doesuserexist&username=Foo/bar'
				=> 'apihelp-doesuserexist-example-invalid-slash',
			'action=doesuserexist&username=MediaWiki default|mediaWiki default|Foo/bar'
				=> 'apihelp-doesuserexist-example-multi',
		];
	}
}
