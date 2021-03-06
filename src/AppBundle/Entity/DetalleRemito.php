<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DetalleRemito
 *
 * @ORM\Table(name="detalle_remito", indexes={@ORM\Index(name="id_producto", columns={"id_producto"}), @ORM\Index(name="id_remito", columns={"id_remito"})})
 * @ORM\Entity
 */
class DetalleRemito
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var \AppBundle\Entity\Producto
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Producto")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_producto", referencedColumnName="id")
     * })
     */
    private $idProducto;

    /**
     * @var \AppBundle\Entity\Remito
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Remito")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_remito", referencedColumnName="id")
     * })
     */
    private $idRemito;



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
     * Set idProducto
     *
     * @param \AppBundle\Entity\Producto $idProducto
     *
     * @return DetalleRemito
     */
    public function setIdProducto(\AppBundle\Entity\Producto $idProducto = null)
    {
        $this->idProducto = $idProducto;

        return $this;
    }

    /**
     * Get idProducto
     *
     * @return \AppBundle\Entity\Producto
     */
    public function getIdProducto()
    {
        return $this->idProducto;
    }

    /**
     * Set idRemito
     *
     * @param \AppBundle\Entity\Remito $idRemito
     *
     * @return DetalleRemito
     */
    public function setIdRemito(\AppBundle\Entity\Remito $idRemito = null)
    {
        $this->idRemito = $idRemito;

        return $this;
    }

    /**
     * Get idRemito
     *
     * @return \AppBundle\Entity\Remito
     */
    public function getIdRemito()
    {
        return $this->idRemito;
    }
}
