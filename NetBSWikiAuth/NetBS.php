<?php

class NetBS
{
    /**
     * @var NetBS
     */
    private static $netBS   = null;

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

    private function __construct($config)
    {
        $this->host             = $config['host'];
        $this->database         = $config['database'];
        $this->username         = $config['username'];
        $this->password         = $config['password'];
        $this->table            = $config['table'];
        $this->usernameColumn   = $config['usernameColumn'];
        $this->hashColumn       = $config['hashColumn'];
        $this->saltColumn       = $config['saltColumn'];
        $this->isAdminColumn    = $config['adminColumn'];
    }

    public static function getInstance($config) {

        if(self::$netBS)
            return self::$netBS;

        self::$netBS = new NetBS($config);
        return self::$netBS;
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