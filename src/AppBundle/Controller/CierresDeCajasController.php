<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Entity\CierresDeCajas;
use AppBundle\Entity\CierresDeCajasTransacciones;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class CierresDeCajasController extends Controller
{
    public function indexAction(Request $request,$idCaja)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('cierres de cajas');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);
          $CierresDeCajas = new CierresDeCajas();
          $form = $this->createForm("AppBundle\Form\CierresDeCajasType",$CierresDeCajas);
          $form->handleRequest($request);

          //Se consulta la caja de acuerdo al id que se desea hacer el cierre.
          //$idCaja = $request->query->get('idCaja');
          $caja = $em->getRepository("AppBundle:Cajas")
          ->findBy(array("idCaja"=>$idCaja));

          //retorna el total recaudado por la caja actual.
          $totalRecaudado = $mainService->totalRecaudado($idCaja,$em);

          //Calcula el número de facturas de la cual se va a realizar el cierre de caja.
          $nroFacturasCierre = $mainService->totalFacturasCierre($idCaja,$em);

          if($totalRecaudado > 0){//Para entrar a la vista de cierre de cajas se debe haber realizado por lo menos un recaudo.
            if($form->isSubmitted() && $form->isValid()){
              $vlrEnCaja = $request->request->get('appbundle_cierresdecajas')['vlrEnCaja'];

              if($vlrEnCaja > 0){
                $em->getConnection()->beginTransaction();

                try{
                  $CierresDeCajas->setFechaHoraCierre(new \Datetime('now', new \DateTimeZone('America/Bogota')));
                  $CierresDeCajas->setIdCaja($caja[0]);

                  //Procedimiento para el calculo y seteo del numero de documento o paquete.
                  $nroDocumento = $mainService->getNroDocument($idCaja,$em);
                  $CierresDeCajas->setNroDocumento($nroDocumento);

                  $em->persist($CierresDeCajas);
                  $em->flush($CierresDeCajas);

                  //Auditoria agrega un bloque a la cadena.
                  $dataAudCierresCaja = [
                    "accion"=>"Insert",
                    "tabla"=>"cierres_de_cajas",
                    "id_datos"=>$CierresDeCajas->getIdCierreCaja(),
                    "data"=>$CierresDeCajas->getArrayData()
                  ];

                  $blockchain->addBlock(new Block($dataAudCierresCaja));

                  //ACTIVAR
                  //Setea para cerrar la session de la caja.
                  $caja[0]->setSessionActiva('0');
                  $em->flush($caja);

                  //Auditoria agrega un bloque a la cadena.
                  $dataAudCierresCaja = [
                    "accion"=>"Update",
                    "tabla"=>"cajas",
                    "id_datos"=>$caja[0]->getIdCaja(),
                    "data"=>["session_activa = 0"]
                  ];

                  $blockchain->addBlock(new Block($dataAudCierresCaja));

                  //Proceso para marcar que transacciones ya tienen un cierre de caja
                  $transacciones = $em->getRepository("AppBundle:Transacciones")
                  ->findBy(array(
                    "idUsuario"=>$session->get('idUsuario'),
                    "idCaja" => $idCaja,
                    "isClosed" => 0//Bandera que determina que esta transaccion esta sin cerrar caja.
                  ));

                  foreach ($transacciones as $key => $value) {
                    $trans = $em->getRepository("AppBundle:Transacciones")
                    ->findBy(array("idTransaccion"=>$value->getIdTransaccion()));
                    //Seteamos para guardar y registrar esta transacción como
                    //una transacción al cual se le hizo un cierre de caja
                    $trans[0]->setIsClosed("1");

                    //modificamos el registro en la bd;
                    $em->flush($trans);

                    //Auditoria agrega un bloque a la cadena.
                    $dataAudTrans = [
                      "accion"=>"Update",
                      "tabla"=>"transacciones",
                      "id_datos"=>$trans[0]->getIdTransaccion(),
                      "data"=>["is_closed = 1"]
                    ];

                    $blockchain->addBlock(new Block($dataAudTrans));

                    //Se registra la relacion entre las transaciones y
                    //los cierres de cajas.
                    $cierresTrans = new CierresDeCajasTransacciones();
                    $cierresTrans->setIdTransaccion($value);

                    $hoy = new \Datetime('now');

                    $CieresCajas = $em->getRepository("AppBundle:CierresDeCajas")
                    ->findBy(array(
                      "nroDocumento" => $nroDocumento,
                      //"fechaHoraCierre" => $hoy,
                      "idCaja" => $idCaja,
                      "totalRecaudoCaja" => $totalRecaudado
                    ));

                    $cierresTrans->setIdCierreDeCaja($CieresCajas[0]);

                    //Guardar los datos en la tabla de la bd.
                    $em->persist($cierresTrans);
                    $em->flush($cierresTrans);

                    //Auditoria agrega un bloque a la cadena.
                    $dataAud = [
                      "accion"=>"Insert",
                      "tabla"=>"cierres_de_cajas_transacciones",
                      "id_datos"=>$cierresTrans->getIdCdcTransacciones(),
                      "data"=>$cierresTrans->getArrayData()
                    ];

                    $blockchain->addBlock(new Block($dataAud));

                  }

                  $em->getConnection()->commit();

                  //Registra los bloques de la cadena en la bd.
                  $blockchain->registerChain();

                  //Redirecciona a recaudos.
                  return $this->redirectToRoute('recaudos');
                }catch(Exception $e){
                    $blockchain->chain = []; //Reinicializa la cadena en caso de error.
                    $em->getConnection()->rollback();
                    throw $e;
                }
              }else{
                $mensajes = "Ingrese el Valor en Caja.";
                //Auditoria agrega un bloque a la cadena.
                $dataAudView = [
                  "accion"=>"Load",
                  "tabla"=>"Entro al módulo de Cierres de Cajas",
                  "id_datos"=>"vista cierres de cajas",
                  "data"=>["cargó vista",$mensajes]
                ];

                $blockchain->addBlock(new Block($dataAudView));
                $blockchain->registerChain();

                return $this->render('@AppBundle/CierresDeCajas/CierresDeCajas.html.twig', array(
                    "form" => $form->createView(),
                    "totalRecaudo" => $totalRecaudado,
                    "totalFacturas" => $nroFacturasCierre,
                    "mensajes" => $mensajes
                ));
              }
            }

            //Auditoria agrega un bloque a la cadena.
            $dataAudView = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Cierres de Cajas",
              "id_datos"=>"vista cierres de cajas",
              "data"=>["cargó vista"]
            ];

            $blockchain->addBlock(new Block($dataAudView));
            $blockchain->registerChain();

            return $this->render('@AppBundle/CierresDeCajas/CierresDeCajas.html.twig', array(
                "form" => $form->createView(),
                "totalRecaudo" => $totalRecaudado,
                "totalFacturas" => $nroFacturasCierre
            ));
          }else{
            $mensajes = "No se puede realizar el cierre de caja, debe haber como mínimo un recaudo en esta caja. Si está seguro que desea cerrar esta caja comuníquese con el Administrador de la Empresa para la cual recauda.";
            //Auditoria agrega un bloque a la cadena.
            $dataAudView = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Recaudos",
              "id_datos"=>"vista recaudos",
              "data"=>["cargó vista",$mensajes]
            ];

            $blockchain->addBlock(new Block($dataAudView));
            $blockchain->registerChain();

            return $this->redirectToRoute('recaudos', array(
                "mensajes" => $mensajes
            ));
          }
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
