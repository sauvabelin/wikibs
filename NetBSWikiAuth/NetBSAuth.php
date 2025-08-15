<?php

require_once 'NetBS.php';
require_once __DIR__ . '/vendor/autoload.php';

class NetBSAuth extends AuthPlugin
{
    private $netBS;

    private $config;

    /**
     * @var \Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder
     */
    private $encoder;

    public function __construct($config)
    {
        $this->netBS        = NetBS::getInstance($config);
        $this->config       = $config;
    }

    public function autoCreate()
    {
        return true;
    }

    public function allowPropChange($prop = '')
    {
        return false;
    }

    public function allowPasswordChange()
    {
        return false;
    }

    public function canCreateAccounts()
    {
        return false;
    }

    public function userExists($username)
    {
        return null !== $this->netBS->getUser($username);
    }

    public function authenticate($username, $password)
    {
        $user   = $this->netBS->getUser($username);
        if(!$user) return false;

        $passwordHash   = $user['password'];
        $salt           = $user['salt'];

        return hash_equals($passwordHash, $this->hashPassword($password, $salt));
    }

    public function updateUser(&$user)
    {
        $netBSUser = $this->netBS->getUser($user->getName());

        if(!$netBSUser)
                return false;

        if((int)$netBSUser['wiki_admin'] === 1) {
            $user->addGroup('sysop');
            $user->addGroup('bureaucrat');
        }

        else {
            $user->removeGroup('sysop');
            $user->removeGroup('bureaucrat');
        }

        return true;
    }

    public function initUser(&$user, $autocreate = false)
    {
        return $this->updateUser($user);
    }

    public function addUser($user, $password, $email = '', $realname = '')
    {
        return false;
    }

    public function modifyUITemplate( &$template, &$type )
    {
        $template->set('usedomain',   false); // We do not want a domain name.
        $template->set('create',      false); // Remove option to create new accounts from the wiki.
        $template->set('useemail',    false); // Disable the mail new password box.
    }

    private function hashPassword($password, $salt) {

        return $this->getEncoder()->encodePassword($password, $salt);
    }

    private function getEncoder() {

        if(!$this->encoder)
            $this->encoder  = new \Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder('sha512', true, $this->config['bcrypt_cost']);

        return $this->encoder;
    }
}