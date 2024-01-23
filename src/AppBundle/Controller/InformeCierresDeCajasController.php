<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class InformeCierresDeCajasController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('informe cierres de cajas');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createForm("AppBundle\Form\InformeCierresDeCajasType");
          $form->handleRequest($request);

          //Se consulta la caja de acuerdo al id que se desea hacer el cierre.
          $idCaja = $request->query->get('idCaja');
          $caja = $em->getRepository("AppBundle:Cajas")
          ->findBy(array("idCaja"=>$idCaja));

          //retorna el total recaudado por la caja actual.
          //$totalRecaudado = $mainService->totalRecaudado($idCaja,$em);

          if($form->isSubmitted() && $form->isValid()){
            $fechaInicio = new \Datetime($request->request->get('appbundle_informecierresdecajas')['inicioFechaCierreCaja']);
            $fechaFinal = new \Datetime($request->request->get('appbundle_informecierresdecajas')['finFechaCierreCaja']);

            if($session->get('rol') != 'Cajero' &&  $session->get('rol') != 'Inactivo'){
                $cierres = $em->getRepository("AppBundle:CierresDeCajas")
                ->createQueryBuilder("CDC")
                ->select(array("CDC"))
                ->join("AppBundle:Cajas","C","WITH","C.idCaja = CDC.idCaja")
                ->join("AppBundle:CierresDeCajasTransacciones","CDCT","WITH","CDCT.idCierreDeCaja=CDC.idCierreCaja")
                ->join("AppBundle:Pagos","P","WITH","CDCT.idTransaccion=P.idTransaccion")
                ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","C.idEmpresaSedeAgencia = ESA.idEmpresaSedeAgencia")
                ->where("DATE(CDC.fechaHoraCierre) >= :Inicio")
                ->andWhere("DATE(CDC.fechaHoraCierre) <= :Fin")
                ->andWhere("P.isDeleted = 0")
                ->setParameter("Inicio",$fechaInicio->format("Y-m-d"))
                ->setParameter("Fin",$fechaFinal->format("Y-m-d"))
                ->addGroupBy("CDC.idCaja","CDC.idCierreCaja")
                ->orderBy('CDC.fechaHoraCierre', 'DESC')
                ->getQuery()->getResult();
            }else if($session->get('rol') == 'Cajero'){
                $cierres = $em->getRepository("AppBundle:CierresDeCajas")
                ->createQueryBuilder("CDC")
                ->select(array("CDC"))
                ->join("AppBundle:Cajas","C","WITH","C.idCaja = CDC.idCaja")
                ->join("AppBundle:CierresDeCajasTransacciones","CDCT","WITH","CDCT.idCierreDeCaja=CDC.idCierreCaja")
                ->join("AppBundle:Pagos","P","WITH","CDCT.idTransaccion=P.idTransaccion")
                ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","C.idEmpresaSedeAgencia = ESA.idEmpresaSedeAgencia")
                ->where("DATE(CDC.fechaHoraCierre) >= :Inicio")
                ->andWhere("DATE(CDC.fechaHoraCierre) <= :Fin")
                ->andWhere("P.isDeleted = 0")
                ->andWhere("C.idUsuario = :idUsuario")
                ->setParameter("Inicio",$fechaInicio->format("Y-m-d"))
                ->setParameter("Fin",$fechaFinal->format("Y-m-d"))
                ->setParameter("idUsuario",$session->get("idUsuario"))
                ->addGroupBy("CDC.idCaja","CDC.idCierreCaja")
                ->orderBy('CDC.fechaHoraCierre', 'DESC')
                ->getQuery()->getResult();
            }

            if(count($cierres) > 0){
              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Select",
                "tabla"=>"cierres_de_cajas",
                "id_datos"=>"vista informe cierres de cajas - filtrado",
                "data"=>[$fechaInicio->format("Y-m-d"),$fechaFinal->format("Y-m-d")]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();
              //Retornamos a la vista del informe.
              return $this->render('@AppBundle/Informes/InformeCierresDeCajas.html.twig', array(
                  "form" => $form->createView(),
                  "cierres" => $cierres
              ));
            }else{
              if($session->get('rol') != 'Cajero' &&  $session->get('rol') != 'Inactivo'){
                  $mensajes = "No hay cierres de cajas registradas dentro del rango de fechas seleccionado.";
                  //Auditoria agrega un bloque a la cadena.
                  $dataAudView = [
                    "accion"=>"Load",
                    "tabla"=>"Entro al módulo de Informe Cierre de Cajas",
                    "id_datos"=>"vista informe cierre de cajas",
                    "data"=>["cargó vista",$mensajes]
                  ];

                  $blockchain->addBlock(new Block($dataAudView));
                  $blockchain->registerChain();
                  //Retornamos a la vista del informe.
                  return $this->render('@AppBundle/Informes/InformeCierresDeCajas.html.twig', array(
                      "form" => $form->createView(),
                      "mensajes" => $mensajes
                  ));
              }else if($session->get('rol') == 'Cajero'){
                  $mensajes = "No hay cierres de cajas que Usted haya registrado dentro del rango de fechas seleccionado.";
                  //Auditoria agrega un bloque a la cadena.
                  $dataAudView = [
                    "accion"=>"Load",
                    "tabla"=>"Entro al módulo de Informe Cierre de Cajas",
                    "id_datos"=>"vista informe cierre de cajas",
                    "data"=>["cargó vista",$mensajes]
                  ];

                  $blockchain->addBlock(new Block($dataAudView));
                  $blockchain->registerChain();
                  //Retornamos a la vista del informe.
                  return $this->render('@AppBundle/Informes/InformeCierresDeCajas.html.twig', array(
                      "form" => $form->createView(),
                      "mensajes" => $mensajes
                  ));
              }
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAudView = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Informe Cierre de Cajas",
            "id_datos"=>"vista informe cierre de cajas",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAudView));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Informes/InformeCierresDeCajas.html.twig', array(
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

    public function ExportInformeAction(Request $request,$idCierreCaja,$typeExport)
    {
      $session = $request->getSession();

      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $main = $this->get("main");
        $conf = $main->ConfigappAction();
        $blockchain = new Blockchain($em);

        //Con el ID del cierre se obtiene ese registro para saber el id de la Caja.
        $cajas = $em->getRepository("AppBundle:CierresDeCajas")
        ->findBy(
          array(
            "idCierreCaja"=>$idCierreCaja
          )
        );

        $transacciones = $em->createQuery(
          "SELECT
            P
           FROM AppBundle:Transacciones T
           JOIN AppBundle:CierresDeCajasTransacciones CDCT WITH T.idTransaccion = CDCT.idTransaccion
           JOIN AppBundle:Pagos P WITH P.idTransaccion = T.idTransaccion
           JOIN AppBundle:Facturas F  WITH F.idFactura = P.idFactura
           WHERE T.idUsuario = :idUs AND T.idCaja = :idCaja
           AND CDCT.idCierreDeCaja = :idCierre
           AND T.isClosed = 1
           AND P.isDeleted = 0
          "
        )->setParameter("idUs",$cajas[0]->getIdCaja()->getIdUsuario())
        ->setParameter("idCaja",$cajas[0]->getIdCaja()->getIdCaja())
        ->setParameter("idCierre",$idCierreCaja)
        ->getResult();

        if(count($cajas) > 0){
          $html = $this->renderView("@AppBundle/Informes/PDFInformeCierresDeCajas.html.twig",
             array(
               "config"=>$conf,
               "cajas"=>$cajas,
               "transacciones" => $transacciones,
               "typeExport"=>$typeExport,
               'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
             )
          );

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Select",
            "tabla"=>"Transacciones,CierresDeCajasTransacciones,pagos,facturas",
            "id_datos"=>"vista informe cierres de cajas - filtrado y se generó el informe .".$typeExport.", idCierreCaja = ".$idCierreCaja,
            "data"=>[$cajas[0]->getIdCaja()->getIdUsuario(),$cajas[0]->getIdCaja()->getIdCaja(),$idCierreCaja,$typeExport]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          if($typeExport == 'pdf'){
              ini_set("max_execution_time", "1200");//20 Min.
              ini_set('memory_limit', '1024M');
              //Accede al servicio knp_snappy
               $snappy = $this->get('knp_snappy.pdf');
               $snappy->setTimeout(10000);

               return new Response(
                   $snappy->getOutputFromHtml($html,
                     array(
                         'orientation' => 'Landscape',
                         'images' => true,
                         'enable-javascript' => true
                     )
                   ),
                   200,
                   array(
                       'Content-Type'          => 'application/pdf',
                       'Content-Disposition'   => 'attachment; filename="informeCierresDeCajas.pdf"'
                   )
               );
           }else if($typeExport == 'excel'){
              return new Response(
                  $html,
                  200,
                  array(
                      'Content-Type'          => 'text/vnd.ms-excel; charset=utf-8',
                      'Content-Disposition'   => 'attachment; filename="informeCierresDeCajas.xls"'
                  )
              );
            }else if($typeExport == 'view'){
                return $this->render('@AppBundle/Informes/PDFInformeCierresDeCajas.html.twig', array(
                      "config"=>$conf,
                      "cajas" => $cajas,
                      "transacciones" => $transacciones,
                      "typeExport"=>$typeExport,
                      'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
                ));
            }
        }else{
          //Retornamos a la vista del informe.
          return $this->redirectToRoute('InformeCierresDeCajas', array(
              "mensajes" => "No hay información de pago para la caja seleccionada."
          ));
        }
     }else{
       return $this->redirectToRoute("error",
         array('codigo'=>'100')
       );
     }
    }

}
