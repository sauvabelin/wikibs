<?php

class NetBS
{
    /**
     * @var NetBS
     */
    private static $instance = null;

    private $pdo  = null;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $database;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $usernameColumn;

    /**
     * @var string
     */
    private $hashColumn;

    /**
     * @var string
     */
    private $saltColumn;

    /**
     * @var string
     */
    private $isAdminColumn;

    /**
     * Private constructor for singleton pattern
     *
     * @param array|Config $config Configuration array or Config object
     */
    private function __construct( $config ) {
        // Handle both array and Config object inputs
        if ( $config instanceof Config ) {
            // Convert Config object to array by getting the global variable
            global $wgNetBSAuthConfig;
            $configArray = $wgNetBSAuthConfig ?: [];
        } elseif ( is_array( $config ) ) {
            $configArray = $config;
        } else {
            throw new InvalidArgumentException( 'Config must be an array or Config object' );
        }

        // Validate required configuration
        $required = [ 'host', 'database', 'username', 'password', 'table', 
                     'usernameColumn', 'hashColumn', 'saltColumn', 'adminColumn' ];
        
        foreach ( $required as $key ) {
            if ( !isset( $configArray[$key] ) ) {
                throw new InvalidArgumentException( "Missing required configuration: $key" );
            }
        }

        $this->host = $configArray['host'];
        $this->database = $configArray['database'];
        $this->username = $configArray['username'];
        $this->password = $configArray['password'];
        $this->table = $configArray['table'];
        $this->usernameColumn = $configArray['usernameColumn'];
        $this->hashColumn = $configArray['hashColumn'];
        $this->saltColumn = $configArray['saltColumn'];
        $this->isAdminColumn = $configArray['adminColumn'];
    }

    /**
     * Get singleton instance
     *
     * @param array|Config $config Configuration array or Config object
     * @return NetBS
     */
    public static function getInstance( $config ) {
        if ( self::$instance === null ) {
            self::$instance = new self( $config );
        }
        return self::$instance;
    }

    public function getUser($username) {

    $select = $this->getPdo()->prepare("SELECT {$this->usernameColumn}, {$this->hashColumn}, {$this->saltColumn}, {$this->isAdminColumn} FROM {$this->table} WHERE {$this->usernameColumn} = ?");
        $select->bind_param('s', $username);
        $select->execute();

        $select->bind_result($uname, $hash, $salt, $isAdmin);
        $select->fetch();

        return $uname !== null ? [
            'username' => $uname,
            'password' => $hash,
            'salt' => $salt,
            'wiki_admin' => $isAdmin,
        ] : null;
    }

    private function getPdo() {

        if($this->pdo)
            return $this->pdo;

        $this->pdo = new \mysqli($this->host, $this->username, $this->password, $this->database);
        return $this->pdo;
    }
}