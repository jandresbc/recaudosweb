<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class consultasTransaccionesController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $mainService = $this->get("main");

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('consultas transacciones');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          // if($session->get('rol') != 'Cajero' &&  $session->get('rol') != 'Inactivo'){
          //   if($session->get('rol') != 'Administrador'){
          //
          //   }
          // }else if($session->get('rol') == 'Cajero'){
          //
          // }

          $form = $this->createForm("AppBundle\Form\ConsultasTransaccionesType");
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $nroFactura = $request->request->get('appbundle_consultastransacciones')['nroFactura'];
            $codTransaccion = $request->request->get('appbundle_consultastransacciones')['codigoTransaccion'];

            if($nroFactura != '' && $nroFactura != null || $codTransaccion != '' && $codTransaccion != null){
              $transaccion = $em->createQueryBuilder()
              ->select(array("P"))
              ->from("AppBundle:Pagos","P")
              ->join("AppBundle:Transacciones","T","WITH","P.idTransaccion = T.idTransaccion")
              ->join("AppBundle:Facturas","F","WITH","P.idFactura = F.idFactura")
              ->where("P.isDeleted = 0");

              if($nroFactura != '' && $nroFactura != null){
                $transaccion->andWhere("F.nroFactura = :idFact");
                $transaccion->setParameter("idFact",$nroFactura);
              }

              if($codTransaccion != '' && $codTransaccion != null){
                $transaccion->andWhere("T.nroTransaccion = :nroTrans");
                $transaccion->setParameter("nroTrans",$codTransaccion);
              }

              $transaccion->orderBy('T.fechaHoraTransaccion', 'DESC');
              $transacciones = $transaccion->getQuery()->getResult();

              if(count($transacciones) > 0){
                //Auditoria agrega un bloque a la cadena.
                $dataAud = [
                  "accion"=>"Select",
                  "tabla"=>"Transacciones,Pagos",
                  "id_datos"=>"vista consultas transacciones - filtrado.",
                  "data"=>[$nroFactura,$codTransaccion]
                ];

                $blockchain->addBlock(new Block($dataAud));
                $blockchain->registerChain();

                return $this->render('@AppBundle/Consultas/consultasTransacciones.html.twig', array(
                    "form" => $form->createView(),
                    "transacciones" => $transacciones
                ));
              }else{
                return $this->render('@AppBundle/Consultas/consultasTransacciones.html.twig', array(
                    "form" => $form->createView(),
                    "mensajes" => "No se encontró ninguna transacción asociado al criterio de búsqueda."
                ));
              }
            }else{
                return $this->render('@AppBundle/Consultas/consultasTransacciones.html.twig', array(
                    "form" => $form->createView(),
                    "mensajes" => "Ingrese cualquiera de los campos del formulario."
                ));
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Consultas Transacciones",
            "id_datos"=>"vista consulta transacciones",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Consultas/consultasTransacciones.html.twig', array(
              "form" => $form->createView()
          ));

        }else if($permiso === false){
         return $this->redirectToRoute("error",
           array('codigo'=>'101')
         );
      }
     }else{
       return $this->redirectToRoute("error",
         array('codigo'=>'100')
       );
     }
    }

}
