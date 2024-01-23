<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class InformeRecaudoMatriculaController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        ini_set("max_execution_time", "1200");//20 Min.
        ini_set('memory_limit', '1024M');
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('informe recaudo matricula');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createForm("AppBundle\Form\InformeRecaudoMatriculaType");
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $niu = $request->request->get('appbundle_informerecaudomatricula')['matricula'];
            $fechaInicio = $request->request->get('appbundle_informerecaudomatricula')['fechaInicio'];
            $fechaFinal = $request->request->get('appbundle_informerecaudomatricula')['fechaFin'];
            $exportType = $request->request->get('exportType');

            if($niu != "" || $fechaInicio != "" || $fechaFinal != ""){
                $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
                ->findBy(["idSedeAgencia"=>$session->get('idSedeAgencia')]);

                if($session->get('rol') == 'Superusuario'){
                  $pagos = $em->getRepository("AppBundle:Pagos")
                  ->createQueryBuilder("P")
                  ->select(array("P"))
                  ->join("AppBundle:Transacciones","T","WITH","P.idTransaccion = T.idTransaccion")
                  ->join("AppBundle:Facturas","F","WITH","F.idFactura = P.idFactura")
                  ->join("AppBundle:Cajas","C","WITH","C.idCaja = T.idCaja")
                  ->where("P.isDeleted = 0")
                  ->addGroupBy("P.idPago","T.idEmpresaSedeAgencia");

                }else if($session->get('rol') == 'Administrador' || $session->get('rol') == "Auditor"){
                  $pagos = $em->getRepository("AppBundle:Pagos")
                  ->createQueryBuilder("P")
                  ->select(array("P"))
                  ->join("AppBundle:Transacciones","T","WITH","P.idTransaccion = T.idTransaccion")
                  ->join("AppBundle:Facturas","F","WITH","F.idFactura = P.idFactura")
                  ->join("AppBundle:Cajas","C","WITH","C.idCaja = T.idCaja")
                  ->where("P.isDeleted = 0")
                  ->addGroupBy("P.idPago","T.idEmpresaSedeAgencia");

                }else if($session->get('rol') == 'Administrador Agencias'){
                    $pagos = $em->getRepository("AppBundle:Pagos")
                    ->createQueryBuilder("P")
                    ->select(array("P"))
                    ->join("AppBundle:Transacciones","T","WITH","P.idTransaccion = T.idTransaccion")
                    ->join("AppBundle:Facturas","F","WITH","F.idFactura = P.idFactura")
                    ->where("P.isDeleted = 0")
                    ->addGroupBy("P.idPago","T.idEmpresaSedeAgencia");

                    if($niu == '' || $fechaInicio == '' || $fechaFinal == ''){
                      $pagos->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","ESA.idEmpresaSedeAgencia = T.idEmpresaSedeAgencia")
                      ->join("AppBundle:SedesAgencias","SA","WITH","SA.idSedeAgencia = ESA.idSedeAgencia");
                      $pagos->where("SA.idAgencia = :idAgencia");
                      $pagos->setParameter("idAgencia",$ESAUsuario[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());
                    }
                }else if($session->get('rol') == 'Cajero'){
                    $pagos = $em->getRepository("AppBundle:Pagos")
                    ->createQueryBuilder("P")
                    ->select(array("P"))
                    ->join("AppBundle:Transacciones","T","WITH","P.idTransaccion = T.idTransaccion")
                    ->join("AppBundle:Facturas","F","WITH","F.idFactura = P.idFactura")
                    ->join("AppBundle:Cajas","C","WITH","C.idCaja = T.idCaja")
                    ->where("P.isDeleted = 0")
                    ->addGroupBy("P.idPago","T.idEmpresaSedeAgencia");
                }

                //Filtros del Informe.
                if($niu != null && $niu != ''){
                  $pagos->andWhere("F.matricula like :niu");
                  $pagos->setParameter("niu","%".$niu."%");
                }

                if($fechaInicio != null && $fechaInicio != ''){
                  $fechaInicio = new \Datetime($fechaInicio);
                  $fechaInicio = $fechaInicio->format("Y-m-d");
                  $pagos->andWhere("DATE(P.fechaHoraPago) >= :Inicio");
                  $pagos->setParameter("Inicio",$fechaInicio);

                  if($fechaFinal == null || $fechaFinal == ''){
                    $pagos->andWhere("DATE(P.fechaHoraPago) <= :Fin");
                    $pagos->setParameter("Fin",new \DateTime('now'));
                  }
                }

                if($fechaFinal != null && $fechaFinal != ''){
                  $fechaFinal = new \Datetime($fechaFinal);
                  $fechaFinal = $fechaFinal->format("Y-m-d");
                  if($fechaInicio == null || $fechaInicio == ''){
                    $pagos->andWhere("DATE(P.fechaHoraPago) >= :Inicio");
                    $pagos->setParameter("Inicio",new \DateTime('now'));
                  }

                  $pagos->andWhere("DATE(P.fechaHoraPago) <= :Fin");
                  $pagos->setParameter("Fin",$fechaFinal);
                }
                //Fin Filtros.
                $pagos->orderBy('P.fechaHoraPago', 'DESC');
                $pagos = $pagos->getQuery()->getResult();

                //Cálculo del totalRecaudado.
                $totalRecaudado = 0;
                foreach ($pagos as $key => $value) {
                  $totalRecaudado += $value->getVlrPago();
                }

                if(count($pagos) > 0){
                  //Auditoria agrega un bloque a la cadena.
                  $dataAud = [
                    "accion"=>"Select",
                    "tabla"=>"Pagos",
                    "id_datos"=>"vista informe recaudado por matricula - filtrado y se generó el informe .".$exportType,
                    "data"=>[$niu,$fechaInicio,$fechaFinal,$exportType]
                  ];

                  $blockchain->addBlock(new Block($dataAud));
                  $blockchain->registerChain();

                  if($exportType == 'pdf'){
                    //Exporta el informe.
                    return $this->ExportInforme($pagos,$totalRecaudado,"pdf");
                  }else if($exportType == 'xls'){
                    //Exporta el informe.
                    return $this->ExportInforme($pagos,$totalRecaudado,"excel");
                  }
                }else{
                    //Retornamos a la vista del informe.
                    return $this->render('@AppBundle/Informes/InformeRecaudoMatricula.html.twig', array(
                        "form" => $form->createView(),
                        "mensajes" => "No hay pagos registrados en el sistema dentro de los filtros seleccionados."
                    ));
                }
              }else{
                //Retornamos a la vista del informe.
                return $this->render('@AppBundle/Informes/InformeRecaudoMatricula.html.twig', array(
                    "form" => $form->createView(),
                    "mensajes" => "Para generar este informe, debe tener como mínimo seleccionado un filtro."
                ));
              }
            }

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Informe Recaudo por NIU (Matrícula)",
              "id_datos"=>"vista informe recaudo por NIU (Matricula)",
              "data"=>["cargó vista"]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            return $this->render('@AppBundle/Informes/InformeRecaudoMatricula.html.twig', array(
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

    public function ExportInforme($pagos,$totalRecaudado,$typeExport)
    {
      $session = new Session();

      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $main = $this->get("main");
        $conf = $main->ConfigappAction();

        if(count($pagos) > 0){
          $html = $this->renderView("@AppBundle/Informes/PDFInformeRecaudoMatricula.html.twig",
             array(
               "config"=>$conf,
               "pagos"=>$pagos,
               "totalRecaudado"=>$totalRecaudado,
               "typeExport"=>$typeExport,
               'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
             )
          );

          if($typeExport == 'pdf'){
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
                       'Content-Disposition'   => 'attachment; filename="informeRecaudoNIU.pdf"'
                   )
               );
           }else if($typeExport == 'excel'){
              return new Response(
                  $html,
                  200,
                  array(
                      'Content-Type'          => 'text/vnd.ms-excel; charset=utf-8',
                      'Content-Disposition'   => 'attachment; filename="informeRecaudoNIU.xls"'
                  )
              );
            }else if($typeExport == 'view'){
                return $this->render('@AppBundle/Informes/PDFInformeTotalRecaudado.html.twig', array(
                      "config"=>$conf,
                      "pagos" => $pagos,
                      "totalRecaudado"=>$totalRecaudado,
                      "typeExport"=>$typeExport,
                      'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
                ));
            }
        }else{
          //Retornamos a la vista del informe.
          return $this->redirectToRoute('InformeRecaudoMatricula', array(
              "mensajes" => "No hay información de pagos."
          ));
        }
     }else{
       return $this->redirectToRoute("error",
         array('codigo'=>'100')
       );
     }
    }

}
