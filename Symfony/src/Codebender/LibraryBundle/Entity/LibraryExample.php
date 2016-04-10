<?php

namespace Codebender\LibraryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LibraryExample
 *
 * @ORM\Entity
 * @ORM\Table(
 *     indexes={@ORM\Index(name="version_idx", columns={"version_id"})}
 * )
 */
class LibraryExample
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
     * @var Version
     *
     * @ORM\ManyToOne(targetEntity="Version", inversedBy="libraryExamples")
     * @ORM\JoinColumn(name="version_id", referencedColumnName="id")
     */
    private $version;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="path", type="string", length=255)
     */
    private $path;

    /**
     * @var string
     *
     * @ORM\Column(name="boards", type="string", length=2048, nullable = true)
     */
    private $boards;


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
     * @return LibraryExample
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
     * Set path
     *
     * @param string $path
     * @return LibraryExample
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get path
     *
     * @return string 
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set boards
     *
     * @param string $boards
     * @return LibraryExample
     */
    public function setBoards($boards)
    {
        $this->boards = $boards;

        return $this;
    }

    /**
     * Get boards
     *
     * @return string 
     */
    public function getBoards()
    {
        return $this->boards;
    }

    /**
     * Set version
     *
     * @param \Codebender\LibraryBundle\Entity\Version $version
     * @return LibraryExample
     */
    public function setVersion(\Codebender\LibraryBundle\Entity\Version $version = null)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return \Codebender\LibraryBundle\Entity\Version 
     */
    public function getVersion()
    {
        return $this->version;
    }
}
