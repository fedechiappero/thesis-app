<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Arquitecto
 *
 * @ORM\Table(name="arquitecto")
 * @ORM\Entity
 */
class Arquitecto
{
    /**
     * @var integer
     *
     * @ORM\Column(name="matricula_numero", type="integer", nullable=false)
     */
    private $matriculaNumero;

    /**
     * @var \AppBundle\Entity\Personal
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Personal")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id", referencedColumnName="id")
     * })
     */
    private $id;



    /**
     * Set matriculaNumero
     *
     * @param integer $matriculaNumero
     *
     * @return Arquitecto
     */
    public function setMatriculaNumero($matriculaNumero)
    {
        $this->matriculaNumero = $matriculaNumero;

        return $this;
    }

    /**
     * Get matriculaNumero
     *
     * @return integer
     */
    public function getMatriculaNumero()
    {
        return $this->matriculaNumero;
    }

    /**
     * Set id
     *
     * @param \AppBundle\Entity\Personal $id
     *
     * @return Arquitecto
     */
    public function setId(\AppBundle\Entity\Personal $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return \AppBundle\Entity\Personal
     */
    public function getId()
    {
        return $this->id;
    }
}
