<?php

use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PrimaryAuthenticationProvider;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\ILoadBalancer;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * NetBS Primary Authentication Provider for MediaWiki AuthManager
 * 
 * This replaces the deprecated AuthPlugin implementation with the new
 * AuthManager framework introduced in MediaWiki 1.27
 */
class NetBSPrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {

    /** @var NetBS */
    private $netBS;
    
    /** @var array */
    protected $config;

    /**
     * @var \Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder
     */
    private $encoder;

    /**
     * @param array $params Configuration parameters
     */
    public function __construct( array $params = [] ) {
        $this->config = $params;
    }

    /**
     * Factory method to create the provider with proper configuration
     * 
     * @return NetBSPrimaryAuthenticationProvider
     */
    public static function factory() {
        global $wgNetBSAuthConfig;
        return new self( $wgNetBSAuthConfig ?: [] );
    }

    /**
     * Initialize the provider
     */
    public function init( LoggerInterface $logger, AuthManager $manager, Config $config ) {
        parent::init( $logger, $manager, $config );
        
        // Ensure we have valid configuration
        if ( empty( $this->config ) ) {
            wfDebugLog( 'authentication', 'NetBS: No configuration provided, using defaults' );
            global $wgNetBSAuthConfig;
            $this->config = $wgNetBSAuthConfig ?: [];
        }
        
        // Create NetBS instance
        try {
            $this->netBS = NetBS::getInstance( $this->config );
            wfDebugLog( 'authentication', 'NetBS: Provider initialized successfully' );
        } catch ( Exception $e ) {
            wfDebugLog( 'authentication', 'NetBS: Failed to initialize: ' . $e->getMessage() );
            $this->netBS = null;
        }
    }

    /**
     * Get or create NetBS instance
     * 
     * @return NetBS|null
     */
    private function getNetBS() {
        if ( $this->netBS === null ) {
            global $wgNetBSAuthConfig;
            $config = $this->config ?: $wgNetBSAuthConfig ?: [];
            
            if ( empty( $config ) ) {
                wfDebugLog( 'authentication', 'NetBS: No configuration available' );
                return null;
            }
            
            try {
                $this->netBS = NetBS::getInstance( $config );
            } catch ( Exception $e ) {
                wfDebugLog( 'authentication', 'NetBS: Failed to create instance: ' . $e->getMessage() );
                return null;
            }
        }
        
        return $this->netBS;
    }

    /**
     * Return the applicable list of AuthenticationRequests
     *
     * @param string $action One of the AuthManager::ACTION_* constants
     * @param array $options Options are:
     *   - username: Username related to the action, if any. This may be added
     *     to $data under the 'username' key.
     * @return AuthenticationRequest[]
     */
    public function getAuthenticationRequests( $action, array $options ) {
        switch ( $action ) {
            case AuthManager::ACTION_LOGIN:
                // Use MediaWiki's standard password request instead of our custom one
                return [ new \MediaWiki\Auth\PasswordAuthenticationRequest() ];
            case AuthManager::ACTION_CREATE:
                // We don't support account creation
                return [];
            default:
                return [];
        }
    }

    /**
     * Start an authentication flow
     *
     * @param array $reqs AuthenticationRequests
     * @return AuthenticationResponse
     */
    public function beginPrimaryAuthentication( array $reqs ) {
        // Look for PasswordAuthenticationRequest instead of our custom request
        $req = \MediaWiki\Auth\AuthenticationRequest::getRequestByClass( 
            $reqs, 
            \MediaWiki\Auth\PasswordAuthenticationRequest::class 
        );
        
        if ( !$req ) {
            wfDebugLog( 'authentication', 'NetBS: No PasswordAuthenticationRequest found, abstaining' );
            return AuthenticationResponse::newAbstain();
        }

        if ( $req->username === null || $req->password === null ) {
            wfDebugLog( 'authentication', 'NetBS: Missing username or password, abstaining' );
            return AuthenticationResponse::newAbstain();
        }

        wfDebugLog( 'authentication', "NetBS: Attempting authentication for user: {$req->username}" );

        // Get NetBS instance safely
        $netBS = $this->getNetBS();
        if ( !$netBS ) {
            wfDebugLog( 'authentication', 'NetBS: NetBS instance not available' );
            return AuthenticationResponse::newAbstain();
        }

        // Check if user exists in NetBS database
        $netBSUser = $netBS->getUser( $req->username );
        if ( !$netBSUser ) {
            wfDebugLog( 'authentication', "NetBS: User {$req->username} not found in database" );
            return AuthenticationResponse::newFail( 
                wfMessage( 'netbsauth-login-not-exists' ) 
            );
        }

        wfDebugLog( 'authentication', "NetBS: User {$req->username} found, verifying password" );

        // Verify password
        if ( !$this->verifyPassword( $req->password, $netBSUser ) ) {
            wfDebugLog( 'authentication', "NetBS: Password verification failed for user: {$req->username}" );
            return AuthenticationResponse::newFail( 
                wfMessage( 'netbsauth-login-wrong-password' ) 
            );
        }

        wfDebugLog( 'authentication', "NetBS: Authentication successful for user: {$req->username}" );
        // Authentication successful
        return AuthenticationResponse::newPass( $req->username );
    }

    /**
     * Continue an authentication flow
     *
     * @param array $reqs AuthenticationRequests
     * @return AuthenticationResponse
     */
    public function continuePrimaryAuthentication( array $reqs ) {
        // We don't implement multi-step authentication
        return AuthenticationResponse::newAbstain();
    }

    /**
     * Update user data after successful authentication
     *
     * @param User $user User object
     * @param AuthenticationResponse $response Response from authentication
     */
    public function postAuthentication( $user, AuthenticationResponse $response ) {
        if ( $response->status === AuthenticationResponse::PASS ) {
            $this->updateUserRoles( $user );
        }
    }

    /**
     * Test whether the named user exists
     *
     * @param string $username MediaWiki username
     * @param int $flags Bitfield of User:READ_* constants
     * @return bool
     */
    public function testUserExists( $username, $flags = User::READ_NORMAL ) {
        $netBS = $this->getNetBS();
        return $netBS ? $netBS->getUser( $username ) !== null : false;
    }

    /**
     * Test whether the named user can authenticate with this provider
     *
     * @param string $username MediaWiki username
     * @return bool
     */
    public function testUserCanAuthenticate( $username ) {
        $netBS = $this->getNetBS();
        if ( !$netBS ) {
            return false;
        }
        
        $user = $netBS->getUser( $username );
        return $user !== null && !empty( $user['password'] );
    }

    /**
     * Normalize the username for authentication
     *
     * @param string $username
     * @return string|null
     */
    public function providerNormalizeUsername( $username ) {
        // Return username as-is, or null if invalid
        if ( !is_string( $username ) ) {
            return null;
        }
        
        $username = trim( $username );
        if ( $username === '' ) {
            return null;
        }
        
        return $username;
    }

    /**
     * Change a user's credentials
     *
     * @param AuthenticationRequest $req
     * @return AuthenticationResponse
     */
    public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
        // We don't allow password changes through MediaWiki
        return AuthenticationResponse::newAbstain();
    }

    /**
     * Determine whether a property can change
     *
     * @param string $property The property being requested
     * @return bool
     */
    public function providerAllowsPropertyChange( $property ) {
        // We don't allow any property changes through MediaWiki
        return false;
    }

    /**
     * Determine whether authentication data changes are allowed
     *
     * @param AuthenticationRequest $req
     * @param bool $checkData
     * @return StatusValue
     */
    public function providerAllowsAuthenticationDataChange( AuthenticationRequest $req, $checkData = true ) {
        // We don't allow authentication data changes through MediaWiki
        return StatusValue::newGood( 'ignored' );
    }

    /**
     * Return the account creation type
     *
     * @return string One of the TYPE_* constants
     */
    public function accountCreationType() {
        // We don't support account creation through MediaWiki
        return PrimaryAuthenticationProvider::TYPE_NONE;
    }

    /**
     * Validate authentication data
     *
     * @param AuthenticationRequest $req
     * @param bool $checkData
     * @return StatusValue
     */
    public function testForAccountCreation( $user, $creator, array $reqs ) {
        // We don't support account creation
        return StatusValue::newGood( 'ignored' );
    }

    /**
     * Create a new account
     *
     * @param User $user
     * @param User $creator
     * @param array $reqs
     * @return AuthenticationResponse
     */
    public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
        // We don't support account creation
        return AuthenticationResponse::newAbstain();
    }

    private function hashPassword($password, $salt) {
        try {
            return $this->getEncoder()->encodePassword($password, $salt);
        } catch (Exception $e) {
            wfDebugLog('authentication', 'NetBS: Failed to hash password: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getEncoder() {
        if(!$this->encoder) {            
            // Get the actual config array, not the Config object
            global $wgNetBSAuthConfig;
            $configArray = $wgNetBSAuthConfig;
            
            if (empty($configArray)) {
                throw new Exception('NetBS: Configuration array is required but not available');
            }
            
            if (!isset($configArray['bcrypt_cost'])) {
                throw new Exception('NetBS: bcrypt_cost configuration parameter is required');
            }
            
            $this->encoder = new \Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder(
                'sha512', 
                true, 
                $configArray['bcrypt_cost']
            );
        }

        return $this->encoder;
    }

    /**
     * Verify password against NetBS database
     *
     * @param string $password Plain text password
     * @param array $netBSUser User data from NetBS database
     * @return bool
     */
    private function verifyPassword( $password, array $netBSUser ) {
        $passwordHash = $netBSUser['password'];
        $salt = $netBSUser['salt'];

        try {
            $hashedPassword = $this->hashPassword( $password, $salt );
            
            $result = hash_equals( $passwordHash, $hashedPassword );
            wfDebugLog( 'authentication', "NetBS: Password verification result: " . ($result ? 'SUCCESS' : 'FAILURE') );
            
            return $result;
        } catch (Exception $e) {
            wfDebugLog( 'authentication', "NetBS: Password verification failed due to error: " . $e->getMessage() );
            return false;
        }
    }

    /**
     * Update user roles based on NetBS database
     *
     * @param User $user MediaWiki user object
     */
    private function updateUserRoles( User $user ) {
        $netBS = $this->getNetBS();
        if ( !$netBS ) {
            wfDebugLog( 'authentication', 'NetBS: Cannot update user roles - NetBS instance not available' );
            return;
        }
        
        $netBSUser = $netBS->getUser( $user->getName() );
        
        if ( !$netBSUser ) {
            return;
        }

        $isAdmin = (int)$netBSUser['wiki_admin'] === 1;
        
        // Get current groups
        $currentGroups = $user->getGroups();
        $adminGroups = [ 'sysop', 'bureaucrat' ];
        
        if ( $isAdmin ) {
            // Add admin groups if not already present
            foreach ( $adminGroups as $group ) {
                if ( !in_array( $group, $currentGroups ) ) {
                    $user->addGroup( $group );
                }
            }
        } else {
            // Remove admin groups if present
            foreach ( $adminGroups as $group ) {
                if ( in_array( $group, $currentGroups ) ) {
                    $user->removeGroup( $group );
                }
            }
        }
        
        // Save user changes
        $user->saveSettings();
    }
}
