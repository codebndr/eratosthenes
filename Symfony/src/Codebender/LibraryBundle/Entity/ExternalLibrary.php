<?php

namespace Codebender\LibraryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ExternalLibrary
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class ExternalLibrary
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
     * @ORM\Column(name="humanName", type="string", length=255)
     */
    private $humanName;

    /**
     * @var string
     *
     * @ORM\Column(name="machineName", type="string", length=255)
     */
    private $machineName;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=1024)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="owner", type="string", length=255)
     */
    private $owner;

    /**
     * @var string
     *
     * @ORM\Column(name="repo", type="string", length=255)
     */
    private $repo;

    /**
     * @var boolean
     *
     * @ORM\Column(name="verified", type="boolean")
     */
    private $verified;

    /**
     * @var string
     *
     * @ORM\Column(name="lastCommit", type="string", length=255, nullable = true)
     */
    private $lastCommit;

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
     * Set humanName
     *
     * @param string $humanName
     * @return ExternalLibrary
     */
    public function setHumanName($humanName)
    {
        $this->humanName = $humanName;
    
        return $this;
    }

    /**
     * Get humanName
     *
     * @return string 
     */
    public function getHumanName()
    {
        return $this->humanName;
    }

    /**
     * Set machineName
     *
     * @param string $machineName
     * @return ExternalLibrary
     */
    public function setMachineName($machineName)
    {
        $this->machineName = $machineName;
    
        return $this;
    }

    /**
     * Get machineName
     *
     * @return string 
     */
    public function getMachineName()
    {
        return $this->machineName;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return ExternalLibrary
     */
    public function setDescription($description)
    {
        $this->description = $description;
    
        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set owner
     *
     * @param string $owner
     * @return ExternalLibrary
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
    
        return $this;
    }

    /**
     * Get owner
     *
     * @return string 
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Set repo
     *
     * @param string $repo
     * @return ExternalLibrary
     */
    public function setRepo($repo)
    {
        $this->repo = $repo;
    
        return $this;
    }

    /**
     * Get repo
     *
     * @return string 
     */
    public function getRepo()
    {
        return $this->repo;
    }

    /**
     * Set verified
     *
     * @param boolean $verified
     * @return ExternalLibrary
     */
    public function setVerified($verified)
    {
        $this->verified = $verified;
    
        return $this;
    }

    /**
     * Get verified
     *
     * @return boolean 
     */
    public function getVerified()
    {
        return $this->verified;
    }

    /**
     * Set lastCommit
     *
     * @param string $lastCommit
     * @return ExternalLibrary
     */
    public function setLastCommit($lastCommit)
    {
        $this->lastCommit = $lastCommit;

        return $this;
    }

    /**
     * Get verified
     *
     * @return string
     */
    public function getLastCommit()
    {
        return $this->lastCommit;
    }

}
