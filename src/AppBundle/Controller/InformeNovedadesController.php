<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class InformeNovedadesController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('informe novedades');

        if($permiso === true){
          ini_set("max_execution_time", "1200");//20 Min.
          ini_set('memory_limit', '1024M');
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createForm("AppBundle\Form\InformeNovedadesType");
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $mesFacturado = $request->request->get('appbundle_informenovedades')['mesFacturacion'];
            $anioFacturado = $request->request->get('appbundle_informenovedades')['anioFacturacion'];
            $modulo = $request->request->get('appbundle_informenovedades')['modulo'];
            $tipoNovedad = $request->request->get('appbundle_informenovedades')['tipoNovedad'];
            $exportType = $request->request->get('exportType');

            $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
            ->findBy(["idSedeAgencia"=>$session->get('idSedeAgencia')]);

            if($modulo == 'pagos'){
              $sqlNovedades = "
                SELECT
                novedades.fecha_hora_novedad,novedades.modulo_afectado,observaciones_novedad,
                tipo_novedad,pagos.fecha_hora_pago,pagos.vlr_pago,pagos.saldo,banco,fecha_consignacion,nro_consignacion,
                pagos.nro_cheque,pagos.observaciones,metodo_pago,tipo_pago,usuarios.nombre_completo,razon_social,
                facturas.mes_facturado,facturas.anio_facturado
                FROM novedades
                INNER JOIN pagos ON pagos.id_pago = novedades.identificador_data
                INNER JOIN metodos_pago ON pagos.id_metodo_pago = metodos_pago.id_metodo_pago
                INNER JOIN tipo_pagos ON pagos.id_tipo_pago = tipo_pagos.id_tipo_pago
                INNER JOIN empresas ON empresas.id_empresa = novedades.id_empresa
                INNER JOIN usuarios ON usuarios.id_usuario = novedades.id_usuario
                INNER JOIN facturas ON pagos.id_factura = facturas.id_factura
                INNER JOIN tipo_novedades ON tipo_novedades.id_tipo_novedad = novedades.id_tipo_novedad
                WHERE facturas.mes_facturado = ".$mesFacturado." and facturas.anio_facturado = ".$anioFacturado."
                AND pagos.is_deleted = 1
                AND empresas.id_empresa = ".$ESAUsuario[0]->getIdEmpresa()->getIdEmpresa()."
              ";
            }

            if($tipoNovedad != null && $tipoNovedad != ''){
              $sqlNovedades .= " and tipo_novedades.id_tipo_novedad = ".$tipoNovedad;
            }

            $sqlNovedades .= " GROUP By novedades.id_novedad ORDER By novedades.id_novedad DESC";

            $novedades = $mainService->rawQueryDoctrine($sqlNovedades);

            if(count($novedades) > 0){
              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Select",
                "tabla"=>"novedades,".$modulo.",facturas",
                "id_datos"=>"vista informe de novedades - filtrado y se generó el informe .".$exportType,
                "data"=>[$tipoNovedad,$modulo,$mesFacturado,$anioFacturado,$exportType]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              if($exportType == 'csv'){
                $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);
                $output = [];
                foreach ($novedades as $key => $value) {
                  $output[$key]["Fecha/Hora Novedad"] = $value["fecha_hora_novedad"];
                  $output[$key]["Modulo Afectado"] = $value["modulo_afectado"];
                  $output[$key]["Observaciones Novedad"] = $value["observaciones_novedad"];
                  $output[$key]["Tipo Novedad"] = $value["tipo_novedad"];
                  $output[$key]["Fecha/Hora Pago"] = $value["fecha_hora_pago"];
                  $output[$key]["Valor Pago"] = $value["vlr_pago"];
                  $output[$key]["Saldo"] = $value["saldo"];
                  $output[$key]["Banco"] = $value["banco"];
                  $output[$key]["Fecha/Hora Consignación"] = $value["fecha_consignacion"];
                  $output[$key]["Nro. Consignación"] = $value["nro_consignacion"];
                  // $output[$key]["Nro. Cheque"] = $value["nro_cheque"];
                  $output[$key]["Observaciones del Pago"] = $value["observaciones"];
                  $output[$key]["Método Pago"] = $value["metodo_pago"];
                  $output[$key]["Tipo Pago"] = $value["tipo_pago"];
                  $output[$key]["Usuario de la Novedad"] = $value["nombre_completo"];
                  $output[$key]["Mes/Año Facturado"] = $value["mes_facturado"]."/".$value["anio_facturado"];

                  if($session->get('rol') == 'Superusuario'){
                    $output[$key]["Empresa"] = $value["razon_social"];
                  }
                }
                $outputCSV = $serializer->encode($output, 'csv');
                return new Response(
                    $outputCSV,
                    200,
                    array(
                        'Content-Type'          => 'text/csv; charset=utf-8',
                        'Content-Disposition'   => 'attachment; filename="InformeNovedades.csv"'
                    )
                );
              }else if($exportType == 'pdf'){
                //Exporta el informe.
                return $this->ExportInforme($novedades,"pdf");
              }else if($exportType == 'xls'){
                //Exporta el informe.
                return $this->ExportInforme($novedades,"excel");
              }
            }else{
              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Select",
                "tabla"=>"novedades,".$modulo.",facturas",
                "id_datos"=>"vista informe de novedades - filtrado",
                "data"=>[$tipoNovedad,$modulo,$mesFacturado,$anioFacturado,$exportType,"No hay registros de novedades en el sistema dentro de los filtros seleccionados."]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();
              //Retornamos a la vista del informe.
              return $this->render('@AppBundle/Informes/InformeNovedades.html.twig', array(
                  "form" => $form->createView(),
                  "mensajes" => "No hay registros de novedades en el sistema dentro de los filtros seleccionados."
              ));
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Informe de Novedades",
            "id_datos"=>"vista informe de novedades",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Informes/InformeNovedades.html.twig', array(
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

    public function ExportInforme($data,$typeExport)
    {
      $session = new Session();

      if($session->get('auth') == 1){
        ini_set("max_execution_time", "1200");//20 Min.
        ini_set('memory_limit', '1024M');
        $em = $this->getDoctrine()->getManager();

        if(count($data) > 0){
          $main = $this->get("main");
          $conf = $main->ConfigappAction();

          $html = $this->renderView("@AppBundle/Informes/PDFInformeNovedades.html.twig",
             [
               "config"=>$conf,
               "novedades"=>$data,
               "typeExport"=>$typeExport,
               "base_dir" => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
             ]
          );

          if($typeExport == 'pdf'){
              //Accede al servicio knp_snappy
               $snappy = $this->get('knp_snappy.pdf');
               $snappy->setTimeout(10000);
               $snappy->setOption("margin-bottom","10");

               return new Response(
                   $snappy->getOutputFromHtml($html,
                     array(
                         'orientation' => 'Landscape', //Portrait
                         'images' => true,
                         'enable-javascript' => true
                     )
                   ),
                   200,
                   array(
                       'Content-Type'          => 'application/pdf',
                       'Content-Disposition'   => 'attachment; filename="InformeNovedades.pdf"'
                   )
               );
           }else if($typeExport == 'excel'){
              return new Response(
                  $html,
                  200,
                  array(
                      'Content-Type'          => 'text/vnd.ms-excel; charset=utf-8',
                      'Content-Disposition'   => 'attachment; filename="InformeNovedades.xls"'
                  )
              );
            }else if($typeExport == 'view'){
                return $this->render('@AppBundle/Informes/PDFInformeNovedades.html.twig', array(
                      "config"=>$conf,
                      "novedades"=>$data,
                      "typeExport"=>$typeExport,
                      'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
                ));
            }
        }else{
          //Retornamos a la vista del informe.
          return $this->redirectToRoute('InformePagosPeriodosAnteriores', array(
              "mensajes" => "No hay información de pagos anteriores para realizar el informe."
          ));
        }
     }else{
       return $this->redirectToRoute("error",
         array('codigo'=>'100')
       );
     }
    }

}
