<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class InformeCarteraController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('informe cartera');

        if($permiso === true){
          ini_set("max_execution_time", "1200");//20 Min.
          ini_set('memory_limit', '1024M');
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createForm("AppBundle\Form\InformeCarteraType");
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $mesFacturado = $request->request->get('appbundle_informecartera')['mesFacturacion'];
            $anioFacturado = $request->request->get('appbundle_informecartera')['anioFacturacion'];
            $tipoInforme = $request->request->get('appbundle_informecartera')['tipoInforme'];
            $exportType = $request->request->get('exportType');

            if($tipoInforme == 2 && $exportType == "grafica"){
              //Retornamos a la vista del informe.
              return $this->render('@AppBundle/Informes/InformeCartera.html.twig', array(
                  "form" => $form->createView(),
                  "mensajes" => "Para este informe se requiere que esté seleccionado el tipo de informe 'Informe Compilado', para poder continuar por favor selecciónelo."
              ));
            }else {
              //Se agregan variables al request.
              $request->query->set("mes",$mesFacturado);//Agrega un variable al request.
              $request->query->set("anio",$anioFacturado);//Agrega un variable al request.
              $request->query->set("cartera","true");//Agrega un variable al request.
              if($exportType == 'grafica' || $tipoInforme == 1){
                $request->query->set("isAjax","true");//Agrega un variable al request.
              }

              $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
              ->findBy(["idSedeAgencia"=>$session->get('idSedeAgencia')]);

              $cartera = json_decode($mainService->getPagosAction($request)->getContent(),true);

              if(isset($cartera) && $cartera["status"] == 1){

                $valCartera = count($cartera["pagosEmpresas"][$ESAUsuario[0]->getIdEmpresa()->getRazonSocial()]);

                if($valCartera > 0){
                  //Se asigna a la variable $cartera la información de su empresa.
                  $cartera = $cartera["pagosEmpresas"][$ESAUsuario[0]->getIdEmpresa()->getRazonSocial()];
                  //Auditoria agrega un bloque a la cadena.
                  $dataAud = [
                    "accion"=>"Select",
                    "tabla"=>"Pagos,Facturas",
                    "id_datos"=>"vista informe cartera - filtrado y se generó el informe .".$exportType,
                    "data"=>[$mesFacturado,$anioFacturado,$exportType]
                  ];

                  $blockchain->addBlock(new Block($dataAud));
                  $blockchain->registerChain();

                  if(isset($cartera["saldosCartera"])){
                    //Se agrega a cartera, las facturas con saldos pendientes.
                    foreach ($cartera["saldosCartera"] as $k => $val) {
                      array_unshift ( $cartera["cartera"] , $val );
                    }
                  }

                  if($exportType == 'pdf'){
                    //Exporta el informe.
                    return $this->ExportInforme($cartera,"pdf",$tipoInforme);
                  }else if($exportType == 'xls'){
                    //Exporta el informe.
                    return $this->ExportInforme($cartera,"excel",$tipoInforme);
                  }else if($exportType == 'grafica'){
                    $porcRecaudo = (($cartera["totalRecaudoGeneralCartera"] / $cartera["totalFacturado"])*100);
                    $porcCartera = (($cartera["totalCartera"] / $cartera["totalFacturado"])*100);

                    return $this->render('@AppBundle/Informes/InformeCartera.html.twig', array(
                        "form" => $form->createView(),
                        "porcentajeRecaudos" => round($porcRecaudo,2),
                        "porcentajeCartera" => round($porcCartera,2),
                        "totalRecaudoGeneralCartera"=>$cartera["totalRecaudoGeneralCartera"],
                        "totalRecaudoGeneral"=>$cartera["PagosGeneral"],
                        "totalFacturado"=>$cartera["totalFacturado"],
                        "totalCartera"=>$cartera["totalCartera"]
                    ));
                  }
                }else{
                  //Retornamos a la vista del informe.
                  return $this->render('@AppBundle/Informes/InformeCartera.html.twig', array(
                      "form" => $form->createView(),
                      "mensajes" => "No hay registros de cartera en el sistema dentro de los filtros seleccionados."
                  ));
                }
              }else if(isset($cartera) && $cartera['status'] == 0){
                //Retornamos a la vista del informe.
                return $this->render('@AppBundle/Informes/InformeCartera.html.twig', array(
                    "form" => $form->createView(),
                    "mensajes" => $cartera['error']
                ));
              }
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Informe Cartera",
            "id_datos"=>"vista informe cartera",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Informes/InformeCartera.html.twig', array(
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

    public function ExportInforme($cartera,$typeExport,$tipoInforme)
    {
      $session = new Session();

      if($session->get('auth') == 1){
        ini_set("max_execution_time", "1200");//20 Min.
        ini_set('memory_limit', '1024M');
        $em = $this->getDoctrine()->getManager();

        if(count($cartera['pagosxAgencias']) > 0){
          $main = $this->get("main");
          $conf = $main->ConfigappAction();
          $html = $this->renderView("@AppBundle/Informes/PDFInformeCartera.html.twig",
             [
               "config"=>$conf,
               "pagos"=>$cartera['pagosxAgencias'],
               "totalRecaudoGeneralCartera"=>$cartera["totalRecaudoGeneralCartera"],
               "totalRecaudoGeneral"=>$cartera["PagosGeneral"],
               "totalFacturado"=>$cartera["totalFacturado"],
               "cartera"=> $tipoInforme == 2 ? $cartera["cartera"] : "",
               "totalCartera"=>$cartera["totalCartera"],
               "typeExport"=>$typeExport,
               "base_dir" => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
             ]
          );

          if($typeExport == 'pdf'){
              //Accede al servicio knp_snappy
               $snappy = $this->get('knp_snappy.pdf');
               $snappy->setTimeout(10000);

               return new Response(
                   $snappy->getOutputFromHtml($html,
                     array(
                         'orientation' => 'Portrait', //Landscape
                         'images' => true,
                         'enable-javascript' => true
                     )
                   ),
                   200,
                   array(
                       'Content-Type'          => 'application/pdf',
                       'Content-Disposition'   => 'attachment; filename="informeCartera.pdf"'
                   )
               );
           }else if($typeExport == 'excel'){
              return new Response(
                  $html,
                  200,
                  array(
                      'Content-Type'          => 'text/vnd.ms-excel; charset=utf-8',
                      'Content-Disposition'   => 'attachment; filename="informeCartera.xls"'
                  )
              );
            }else if($typeExport == 'view'){
                return $this->render('@AppBundle/Informes/PDFInformeCartera.html.twig', array(
                      "config"=>$conf,
                      "pagos"=>$cartera['pagosxAgencias'],
                      "totalRecaudoGeneral"=>$cartera["totalRecaudoGeneral"],
                      "totalFacturado"=>$cartera["totalFacturado"],
                      "cartera"=>$tipoInforme == 2 ? $cartera["cartera"] : "",
                      "totalCartera"=>$cartera["totalCartera"],
                      "typeExport"=>$typeExport,
                      'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
                ));
            }
        }else{
          //Retornamos a la vista del informe.
          return $this->redirectToRoute('InformeCartera', array(
              "mensajes" => "No hay información de cartera para realizar el informe."
          ));
        }
     }else{
       return $this->redirectToRoute("error",
         array('codigo'=>'100')
       );
     }
    }

}
