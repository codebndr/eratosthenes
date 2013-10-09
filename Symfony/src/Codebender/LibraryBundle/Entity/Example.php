<?php

namespace Codebender\LibraryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Example
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Example
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
     * @ORM\ManyToOne(targetEntity="Codebender\LibraryBundle\Entity\ExternalLibrary")
     **/
    private $library;

    /**
     * @var string
     *
     * @ORM\Column(name="path", type="string", length=255)
     */
    private $path;

    /**
     * @var string
     *
     * @ORM\Column(name="boards", type="string", length=255, nullable = true)
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
     * @return Example
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
     * Set library
     *
     * @param ExternalLibrary $library
     * @return ExternalLibrary
     */
    public function setLibrary($library)
    {
        $this->library = $library;
    
        return $this;
    }

    /**
     * Get library
     *
     * @return ExternalLibrary
     */
    public function getLibrary()
    {
        return $this->library;
    }

    /**
     * Set path
     *
     * @param string $path
     * @return Example
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
     * @return Example
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
