<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\Services\Auth;

use Doctrine\ORM\EntityManager;

/**
 * Doctrine Auth service
 */
class DoctrineAuthService implements \Zend_Auth_Adapter_Interface
{
    /** @var Doctrine\ORM\EntityManager */
    private $em;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    /** @var bool */
    private $is_admin = FALSE;

    /**
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Perform authentication attempt
     *
     * @return \Zend_Auth_Result
     */
    public function authenticate()
    {
        $user = $this->em->getRepository('Newscoop\Entity\User')
            ->findOneBy(array(
            'username' => $this->username,
        ));

        if (empty($user)) {
            return new \Zend_Auth_Result(\Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND, NULL);
        }

        if (!$user->isActive()) {
            return new \Zend_Auth_Result(\Zend_Auth_Result::FAILURE_UNCATEGORIZED, NULL);
        }

        if ($user->isAdmin() != $this->is_admin) {
            return new \Zend_Auth_Result(\Zend_Auth_Result::FAILURE_UNCATEGORIZED, NULL);
        }

        if (!$user->checkPassword($this->password)) {
            return new \Zend_Auth_Result(\Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID, NULL);
        }

        $this->em->flush(); // store updated password
        return new \Zend_Auth_Result(\Zend_Auth_Result::SUCCESS, $user->getId());
    }

    /**
     * Set username
     *
     * @param string $username
     * @return Newscoop\Services\Auth\DoctrineAuthService
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return Newscoop\Services\Auth\DoctrineAuthService
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Set is admin constrain
     *
     * @param bool $admin
     * @return Newscoop\Services\AuthService
     */
    public function setAdmin($admin = TRUE)
    {
        $this->is_admin = (bool) $admin;
        return $this;
    }
}
