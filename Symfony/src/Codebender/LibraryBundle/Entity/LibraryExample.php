<?php

namespace Codebender\LibraryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LibraryExample
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="LibraryExample",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="version_idx", columns={"version_id"})}
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
     * @var string
     *
     * @ORM\Column(name="version_id", type="string", length=255)
     */
    private $version_id;

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
     * Set version_id
     *
     * @param string $version_id
     * @return LibraryExample
     */
    public function setVersionId($version_id)
    {
        $this->version_id = $version_id;
    
        return $this;
    }

    /**
     * Get version_id
     *
     * @return string 
     */
    public function getVersionId()
    {
        return $this->version_id;
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

}
