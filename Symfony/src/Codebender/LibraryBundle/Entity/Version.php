<?php

namespace Codebender\LibraryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Version
 *
 * @ORM\Table(
 *     uniqueConstraints={@ORM\UniqueConstraint(name="folders_idx", columns={"library_id", "folder_name"})},
 *     indexes={@ORM\Index(name="libraries_idx", columns={"library_id"})}
 * )
 * @ORM\Entity
 */
class Version
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
     * @var Library
     *
     * @ORM\ManyToOne(targetEntity="Library", inversedBy="versions")
     * @ORM\JoinColumn(name="library_id", referencedColumnName="id")
     */
    private $library;

    /**
     * @var string
     *
     * @ORM\Column(name="version", type="string", length=255)
     */
    private $version;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=2048, nullable = true)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="notes", type="text", nullable = true)
     */
    private $notes;

    /**
     * @var string
     *
     * @ORM\Column(name="source_url", type="string", length=512, nullable = true)
     */
    private $sourceUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="release_commit", type="string", length=255, nullable = true)
     */
    private $releaseCommit;

    /**
     * @var string
     *
     * @ORM\Column(name="folder_name", type="string", length=255)
     */
    private $folderName;

    /**
     * @ORM\OneToMany(targetEntity="LibraryExample", mappedBy="version")
     */
    private $libraryExamples;

    /**
     * @ORM\ManyToMany(targetEntity="Architecture")
     * @ORM\JoinTable(name="ArchitectureVersion",
     *      joinColumns={@ORM\JoinColumn(name="version_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="architecture_id", referencedColumnName="id")}
     *      )
     */
    private $architectures;
    /**
     * Constructor
     */

    public function __construct()
    {
        $this->libraryExamples = new \Doctrine\Common\Collections\ArrayCollection();
        $this->architectures = new \Doctrine\Common\Collections\ArrayCollection();
    }

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
     * Set version
     *
     * @param string $version
     * @return Version
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return string 
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return Version
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
     * Set notes
     *
     * @param string $notes
     * @return Version
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
     * Set sourceUrl
     *
     * @param string $sourceUrl
     * @return Version
     */
    public function setSourceUrl($sourceUrl)
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    /**
     * Get sourceUrl
     *
     * @return string 
     */
    public function getSourceUrl()
    {
        return $this->sourceUrl;
    }

    /**
     * Set releaseCommit
     *
     * @param string $releaseCommit
     * @return Version
     */
    public function setReleaseCommit($releaseCommit)
    {
        $this->releaseCommit = $releaseCommit;

        return $this;
    }

    /**
     * Get releaseCommit
     *
     * @return string 
     */
    public function getReleaseCommit()
    {
        return $this->releaseCommit;
    }

    /**
     * Set folderName
     *
     * @param string $folderName
     * @return Version
     */
    public function setFolderName($folderName)
    {
        $this->folderName = $folderName;

        return $this;
    }

    /**
     * Get folderName
     *
     * @return string 
     */
    public function getFolderName()
    {
        return $this->folderName;
    }

    /**
     * Set library
     *
     * @param \Codebender\LibraryBundle\Entity\Library $library
     * @return Version
     */
    public function setLibrary(\Codebender\LibraryBundle\Entity\Library $library = null)
    {
        $this->library = $library;

        return $this;
    }

    /**
     * Get library
     *
     * @return \Codebender\LibraryBundle\Entity\Library 
     */
    public function getLibrary()
    {
        return $this->library;
    }

    /**
     * Add libraryExample
     *
     * @param \Codebender\LibraryBundle\Entity\LibraryExample $libraryExample
     * @return Version
     */
    public function addLibraryExample(\Codebender\LibraryBundle\Entity\LibraryExample $libraryExample)
    {
        $this->libraryExamples[] = $libraryExample;

        return $this;
    }

    /**
     * Remove libraryExample
     *
     * @param \Codebender\LibraryBundle\Entity\LibraryExample $libraryExample
     */
    public function removeLibraryExample(\Codebender\LibraryBundle\Entity\LibraryExample $libraryExample)
    {
        $this->libraryExamples->removeElement($libraryExample);
    }

    /**
     * Get libraryExamples
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getLibraryExamples()
    {
        return $this->libraryExamples;
    }

    /**
     * Add architecture
     *
     * @param \Codebender\LibraryBundle\Entity\Architecture $architecture
     * @return Version
     */
    public function addArchitecture(\Codebender\LibraryBundle\Entity\Architecture $architecture)
    {
        $this->architectures[] = $architecture;

        return $this;
    }

    /**
     * Remove architecture
     *
     * @param \Codebender\LibraryBundle\Entity\Architecture $architecture
     */
    public function removeArchitecture(\Codebender\LibraryBundle\Entity\Architecture $architecture)
    {
        $this->architectures->removeElement($architecture);
    }

    /**
     * Get architectures
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getArchitectures()
    {
        return $this->architectures;
    }
}
