<?php

namespace AppBundle\Controller;

use AppBundle\Entity\DetalleOrden;
use AppBundle\Entity\DetallePedidoOrden;
use AppBundle\Entity\OrdenCompra;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ordencompra controller.
 * @Security("is_granted('ROLE_ADMIN')")
 * @Route("ordencompra")
 */
class OrdenCompraController extends Controller
{
    /**
     * Lists all ordenCompra entities.
     *
     * @Route("/", name="ordencompra_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $query = $em->createQuery("
            SELECT oc.id, oc.numero, oc.fechaEmision, oc.total, u.username AS username, pj.razonSocial AS proveedorRazonSocial, o.nombre AS obraNombre FROM AppBundle:OrdenCompra oc
            INNER JOIN AppBundle:EncCompMatCons ec WITH oc.idEncComp = ec.id
            INNER JOIN AppBundle:User u WITH ec.id = u.id
            INNER JOIN AppBundle:Proveedor p WITH oc.idProveedor = p.id
            INNER JOIN AppBundle:PersonaJuridica pj WITH p.id = pj.id
            INNER JOIN AppBundle:Obra o WITH oc.idObra = o.id
            ORDER BY oc.fechaEmision DESC
        ");

        $ordenCompras = $query->getResult();

        return $this->render('ordencompra/index.html.twig', array(
            'ordenCompras' => $ordenCompras,
        ));
    }

    /**
     * Creates a new ordenCompra entity.
     *
     * @Route("/new", name="ordencompra_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        if ($request->isMethod('POST')) {

            //values from view
            $proveedor = $request->request->get('proveedorselected');
            $obra = $request->request->get('idobra');
            $numero = $request->request->get('numero');
            $fecha = new \DateTime('now');
            $total = $request->request->get('subtotal');

            //values from db
            $proveedor = $em->getRepository('AppBundle:Proveedor')->find($proveedor);
            //future update, find user logged, this reflect who made the order
            $usuario = $em->getRepository('AppBundle:EncCompMatCons')->find(1);//se se.. una garcha
            $obra = $em->getRepository('AppBundle:Obra')->find($obra);

            $ordenCompra = new OrdenCompra();
            $ordenCompra->setIdProveedor($proveedor);
            $ordenCompra->setIdObra($obra);
            $ordenCompra->setIdEncComp($usuario);
            $ordenCompra->setNumero($numero);
            $ordenCompra->setFechaEmision($fecha);
            $ordenCompra->setTotal($total);

            $em->persist($ordenCompra);
            $em->flush();

            //details (product + quantity)
            $arrproductos = $request->request->get('arrproductos');
            $indice = 0;
            $arrprod = explode(',', $arrproductos);//paso a arreglo
            $longitud = count($arrprod);
            while($indice < $longitud){
                $cantidad = $arrprod[$indice+1];

                if($cantidad > 0){//may be 0 because there are existing stock available or for some logic reason that product is not included in the order
                    $producto = $em->getRepository('AppBundle:Producto')->find($arrprod[$indice]);

                    $dql = "
                    SELECT p.id FROM AppBundle:Precio p
                        WHERE p.idProducto = :idproducto AND p.idProveedor = :idproveedor AND
                        p.fechaUltimaActualizacion IN (SELECT MAX(p2.fechaUltimaActualizacion) FROM AppBundle:Precio p2
                                                           GROUP BY p2.idProducto, p2.idProveedor)
                    ";

                    $query = $em->createQuery($dql)
                        ->setParameter('idproducto', $producto->getId())
                        ->setParameter('idproveedor', $proveedor->getId());

                    //will throw an exception if more than one result are found, or if no result is found
                    $idprecio = $query->getSingleResult();

                    $precio = $em->getRepository('AppBundle:Precio')->findOneBy(array('id'=>$idprecio));

                    $detalleOrden = new DetalleOrden();
                    $detalleOrden
                        ->setIdOrden($ordenCompra)
                        ->setIdPrecio($precio)
                        ->setPrecioUnitario($precio->getPrecio())
                        ->setCantidad($cantidad);

                    $em->persist($detalleOrden);
                    $em->flush();


                }
                $indice += 2;
            }

            //details (which request was included in this buy order)
            $arrdetalles = $request->request->get('arrdetalles');
            $indice = 0;
            $arrped = explode(',', $arrdetalles);//paso a arreglo
            $longitud = count($arrped);
            while($indice < $longitud){
                $pedido = $em->getRepository('AppBundle:Pedido')->find($arrped[$indice]);

                $detallePedidoOrden = new DetallePedidoOrden();
                $detallePedidoOrden
                    ->setIdOrden($ordenCompra)
                    ->setIdPedido($pedido);

                $em->persist($detallePedidoOrden);
                $em->flush();

                //                                              3 = order submitted
                $em->getRepository('AppBundle:EstadoPedido')->newStatus($pedido, 3);

                $indice += 1;
            }

            return $this->redirectToRoute('ordencompra_show', array('id' => $ordenCompra->getId()));
        }

        $obras = $em->getRepository('AppBundle:Obra')->findAll();

        $dql = "
            SELECT IDENTITY(p.id) AS id, pj.razonSocial AS razonSocial FROM AppBundle:Proveedor p
            INNER JOIN AppBundle:PersonaJuridica pj WITH pj.id = p.id
        ";

        $query = $em->createQuery($dql);

        $proveedores = $query->getResult();

        //select last order number
        $query = $em->createQuery("SELECT MAX(op.numero) AS numero FROM AppBundle:OrdenCompra op");
        $ordenCompra = $query->getResult();

        return $this->render('ordencompra/new.html.twig', array(
            'proveedores' => $proveedores,
            'obras' => $obras,
            'ordencompra' => $ordenCompra[0]
        ));
    }

    /**
     * Finds and displays a ordenCompra entity.
     *
     * @Route("/{id}", name="ordencompra_show")
     * @Method("GET")
     */
    public function showAction(OrdenCompra $ordenCompra)
    {
        $deleteForm = $this->createDeleteForm($ordenCompra);

        $em = $this->getDoctrine()->getManager();

        $dqlOrden = "
            SELECT oc.id, oc.numero, oc.fechaEmision, oc.total, u.username AS username, pj.razonSocial AS proveedorRazonSocial, o.nombre AS obraNombre FROM AppBundle:OrdenCompra oc
            INNER JOIN AppBundle:User u WITH oc.idEncComp = u.id
            INNER JOIN AppBundle:Proveedor p WITH oc.idProveedor = p.id
            INNER JOIN AppBundle:PersonaJuridica pj WITH p.id = pj.id
            INNER JOIN AppBundle:Obra o WITH oc.idObra = o.id
            WHERE oc.id = :id
        ";

        $queryOrdenes = $em->createQuery($dqlOrden)
            ->setParameter('id',$ordenCompra->getId());

        $dqlDetalles = "
            SELECT pro.nombre, d.cantidad, d.precioUnitario FROM AppBundle:DetalleOrden d
            INNER JOIN AppBundle:Precio pre WITH pre.id = d.idPrecio
            INNER JOIN AppBundle:Producto pro WITH pro.id = pre.idProducto
            WHERE d.idOrden = :id
        ";

        $queryDetalles = $em->createQuery($dqlDetalles)
            ->setParameter('id',$ordenCompra->getId());

        $ordenCompra = $queryOrdenes->getResult();
        $detalles = $queryDetalles->getResult();

        dump($detalles);

        return $this->render('ordencompra/show.html.twig', array(
            'ordenCompra' => $ordenCompra[0],
            'detalles' => $detalles,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing ordenCompra entity.
     *
     * @Route("/{id}/edit", name="ordencompra_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, OrdenCompra $ordenCompra)
    {
        $deleteForm = $this->createDeleteForm($ordenCompra);
        $editForm = $this->createForm('AppBundle\Form\OrdenCompraType', $ordenCompra);
        $editForm->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('ordencompra_edit', array('id' => $ordenCompra->getId()));
        }


        $dqlDetalles = "
            /*SELECT p.id AS id, d.cantidad AS cantidad, p.nombre AS nombre FROM AppBundle:DetalleOrdenCompra d
            INNER JOIN AppBundle:Producto p WITH d.idProducto = p.id
            WHERE d.idPedido = :id*/
        ";

        $queryDetalles = $em->createQuery($dqlDetalles)
            ->setParameter('id', $ordenCompra->getId());

        $detalles = $queryDetalles->getResult();

        return $this->render('ordencompra/edit.html.twig', array(
            'ordenCompra' => $ordenCompra,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a ordenCompra entity.
     *
     * @Route("/{id}", name="ordencompra_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, OrdenCompra $ordenCompra)
    {
        $form = $this->createDeleteForm($ordenCompra);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($ordenCompra);
            $em->flush();
        }

        return $this->redirectToRoute('ordencompra_index');
    }

    /**
     * Creates a form to delete a ordenCompra entity.
     *
     * @param OrdenCompra $ordenCompra The ordenCompra entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(OrdenCompra $ordenCompra)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('ordencompra_delete', array('id' => $ordenCompra->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
    * Creates a pdf file for ordenCompra.
    *
    * @Route("/pdf/{id}", name="ordencompra_pdf")
    */
    public function crearPdf(OrdenCompra $ordenCompra){
        $em = $this->getDoctrine()->getManager();

        $dql = "
            SELECT oc.id, oc.numero, oc.fechaEmision, oc.total, pj.razonSocial AS proveedorRazonSocial, pe.direccion AS proveedorDireccion FROM AppBundle:OrdenCompra oc
            INNER JOIN AppBundle:Proveedor p WITH oc.idProveedor = p.id
            INNER JOIN AppBundle:PersonaJuridica pj WITH p.id = pj.id
            INNER JOIN AppBundle:Persona pe WITH pj.id = pe.id
            WHERE oc.id = :id
        ";

        $queryOrdenes = $em->createQuery($dql)
            ->setParameter('id',$ordenCompra->getId());

        $dqlDetalles = "
            SELECT pro.nombre, d.cantidad, d.precioUnitario FROM AppBundle:DetalleOrden d
            INNER JOIN AppBundle:Precio pre WITH pre.id = d.idPrecio
            INNER JOIN AppBundle:Producto pro WITH pro.id = pre.idProducto
            WHERE d.idOrden = :id
        ";

        $queryDetalles = $em->createQuery($dqlDetalles)
            ->setParameter('id',$ordenCompra->getId());

        $ordenCompra = $queryOrdenes->getResult();
        $detalles = $queryDetalles->getResult();

        $ordenCompra = $ordenCompra[0];
        $twigoutput = $this->renderView('ordencompra/pdf.html.twig', array(
            'ordenCompra' => $ordenCompra,
            'detalles' => $detalles
        ));

        $numero = $ordenCompra['numero'];

        $nombreArchivo = "ordenCompra-${numero}.pdf";

        return new PdfResponse(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($twigoutput),
            $nombreArchivo
        );
    }

    /**
     * Busca ordenes de compra
     *
     * @Route("/ajax", name="buscar_ordenes_compra")
     * @return JsonResponse|Response
     */
    public function buscarOrdenesCompra(Request $request){
        if ($request->isXmlHttpRequest()) {
            if ($request->request->get('desde')) {
                $fechadesde = $request->request->get('desde');
                $idProveedor = $request->request->get('idProveedor');
                $em = $this->getDoctrine()->getManager();

                $dql = "          
                    SELECT oc.id, oc.numero, DATE_FORMAT(oc.fechaEmision, '%d-%m-%Y') AS fechaEmision, pj.razonSocial, o.nombre FROM AppBundle:OrdenCompra oc
                    INNER JOIN AppBundle:Proveedor p WITH oc.idProveedor = p.id 
                    INNER JOIN AppBundle:PersonaJuridica pj WITH pj.id = p.id
                    INNER JOIN AppBundle:Obra o WITH oc.idObra = o.id
                    WHERE p.id = :idproveedor AND oc.fechaEmision BETWEEN :desde AND :hasta
                ";

                $fechahasta = new \DateTime('now');

                $query = $em->createQuery($dql)
                    ->setParameter('idproveedor', $idProveedor)
                    ->setParameter('desde', $fechadesde)
                    ->setParameter('hasta', $fechahasta)
                ;

                $ordenes = $query->getResult();

                dump($ordenes);
                return new JsonResponse($ordenes);
            }
        }
        return $this->redirectToRoute('pedido_new');
    }
}
