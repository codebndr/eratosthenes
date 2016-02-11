<?php

namespace Codebender\LibraryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Preference
 *
 * @ORM\Table(
 *     uniqueConstraints={@ORM\UniqueConstraint(name="search_idx", columns={"library_id", "partner_id"})}
 * )
 * @ORM\Entity
 */
class Preference
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
     * @ORM\ManyToOne(targetEntity="Library")
     * @ORM\JoinColumn(name="library_id", referencedColumnName="id")
     */
    private $library;

    /**
     * @var Partner
     *
     * @ORM\ManyToOne(targetEntity="Partner", inversedBy="preferences")
     * @ORM\JoinColumn(name="partner_id", referencedColumnName="id")
     */
    private $partner;

    /**
     * @var Version
     *
     * @ORM\ManyToOne(targetEntity="Version")
     * @ORM\JoinColumn(name="version_id", referencedColumnName="id")
     */
    private $version;

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
     * Set library
     *
     * @param \Codebender\LibraryBundle\Entity\Library $library
     * @return Preference
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
     * Set partner
     *
     * @param \Codebender\LibraryBundle\Entity\Partner $partner
     * @return Preference
     */
    public function setPartner(\Codebender\LibraryBundle\Entity\Partner $partner = null)
    {
        $this->partner = $partner;

        return $this;
    }

    /**
     * Get partner
     *
     * @return \Codebender\LibraryBundle\Entity\Partner
     */
    public function getPartner()
    {
        return $this->partner;
    }

    /**
     * Set version
     *
     * @param \Codebender\LibraryBundle\Entity\Version $version
     * @return Preference
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
