<?php

namespace Codebender\LibraryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Partner
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="Partner",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="auth_key_idx", columns={"auth_key"})}
 * )
 */
class Partner
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="auth_key", type="string", length=255)
     */
    private $auth_key;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Partner
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set auth_key
     *
     * @param string $authKey
     * @return Partner
     */
    public function setAuthKey($authKey)
    {
        $this->auth_key = $authKey;

        return $this;
    }

    /**
     * Get auth_key
     *
     * @return string
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }
}
