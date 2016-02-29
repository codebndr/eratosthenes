<?php

namespace Codebender\LibraryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Library
 *
 * @ORM\Table(
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="header_idx", columns={"default_header", "folder_name"})
 *     }
 * )
 * @ORM\Entity
 */
class Library
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
     * @ORM\Column(name="default_header", type="string", length=255)
     */
    private $default_header;

    /**
     * @var string
     *
     * @ORM\Column(name="folder_name", type="string", length=255)
     */
    private $folder_name;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=2048)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="owner", type="string", length=255, nullable = true)
     */
    private $owner;

    /**
     * @var string
     *
     * @ORM\Column(name="repo", type="string", length=255, nullable = true)
     */
    private $repo;

    /**
     * @var string
     *
     * @ORM\Column(name="branch", type="string", length=255, nullable = true)
     */
    private $branch;

    /**
     * @var string
     *
     * @ORM\Column(name="in_repo_path", type="string", length=255, nullable = true)
     */
    private $inRepoPath;

    /**
     * @var string
     *
     * @ORM\Column(name="notes", type="text", nullable = true)
     */
    private $notes;

    /**
     * @var boolean
     *
     * @ORM\Column(name="verified", type="boolean")
     */
    private $verified;

    /**
     * @var boolean
     *
     * @ORM\Column(name="active", type="boolean")
     */
    private $active;

    /**
     * @var string
     *
     * @ORM\Column(name="last_commit", type="string", length=255, nullable = true)
     */
    private $last_commit;

    /**
     * @var string
     *
     * @ORM\Column(name="url", type="string", length=512, nullable = true)
     */
    private $url;

    /**
     * @ORM\OneToMany(targetEntity="Version", mappedBy="library")
     */
    private $versions;

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
     * @return Library
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
     * Set default_header
     *
     * @param string $defaultHeader
     * @return Library
     */
    public function setDefaultHeader($defaultHeader)
    {
        $this->default_header = $defaultHeader;

        return $this;
    }

    /**
     * Get default_header
     *
     * @return string
     */
    public function getDefaultHeader()
    {
        return $this->default_header;
    }

    /**
     * Set folder_name
     *
     * @param string $folderName
     * @return Library
     */
    public function setFolderName($folderName)
    {
        $this->folder_name = $folderName;

        return $this;
    }

    /**
     * Get folder_name
     *
     * @return string
     */
    public function getFolderName()
    {
        return $this->folder_name;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return Library
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
     * @return Library
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
     * @return Library
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
     * Set branch
     *
     * @param string $branch
     * @return Version
     */
    public function setBranch($branch)
    {
        $this->branch = $branch;

        return $this;
    }

    /**
     * Get branch
     *
     * @return string
     */
    public function getBranch()
    {
        return $this->branch;
    }

    /**
     * Set inRepoPath
     *
     * @param string $inRepoPath
     * @return Version
     */
    public function setInRepoPath($inRepoPath)
    {
        $this->inRepoPath = $inRepoPath;

        return $this;
    }

    /**
     * Get inRepoPath
     *
     * @return string
     */
    public function getInRepoPath()
    {
        return $this->inRepoPath;
    }

    /**
     * Set notes
     *
     * @param string $notes
     * @return Library
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Set verified
     *
     * @param boolean $verified
     * @return Library
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
     * Set active
     *
     * @param boolean $active
     * @return Library
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set last_commit
     *
     * @param string $lastCommit
     * @return Library
     */
    public function setLastCommit($lastCommit)
    {
        $this->last_commit = $lastCommit;

        return $this;
    }

    /**
     * Get last_commit
     *
     * @return string
     */
    public function getLastCommit()
    {
        return $this->last_commit;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return Library
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->versions = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add version
     *
     * @param \Codebender\LibraryBundle\Entity\Version $version
     * @return Library
     */
    public function addVersion(\Codebender\LibraryBundle\Entity\Version $version)
    {
        $this->versions[] = $version;

        return $this;
    }

    /**
     * Remove version
     *
     * @param \Codebender\LibraryBundle\Entity\Version $version
     */
    public function removeVersion(\Codebender\LibraryBundle\Entity\Version $version)
    {
        $this->versions->removeElement($version);
    }

    /**
     * Get versions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * Get the metadata of the library
     *
     * @return array
     */
    public function getLiraryMeta()
    {
        return array(
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'verified' => $this->getVerified(),
            'gitOwner' => $this->getOwner(),
            'gitRepo' => $this->getRepo(),
            'url' => $this->getUrl(),
            'active' => $this->getActive(),
            'gitBranch' => $this->getBranch(),
            'gitLastCommit' => $this->getLastCommit(),
            'gitInRepoPath' => $this->getInRepoPath(),
            'libraryNotes' => $this->getNotes()
        );
    }
}
