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

class InformePagosPeriodosAnterioresController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('informe pagos periodos anteriores');

        if($permiso === true){
          ini_set("max_execution_time", "1200");//20 Min.
          ini_set('memory_limit', '1024M');
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createForm("AppBundle\Form\InformePagosPeriodosAnterioresType");
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $mesFacturado = $request->request->get('appbundle_informecartera')['mesFacturacion'];
            $anioFacturado = $request->request->get('appbundle_informecartera')['anioFacturacion'];
            $exportType = $request->request->get('exportType');

            $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
            ->findBy(["idSedeAgencia"=>$session->get('idSedeAgencia')]);

            //Consulta de fecha de vencimiento registrada en la bd de la facturación del periodo seleccionado.
            $fechaVenc = $em->createQuery("
              Select Max(F.fechaVencimiento) From AppBundle:Facturas F
              where F.mesFacturado = :mesFact and F.anioFacturado = :anioFact
            ")->setParameter("mesFact",$mesFacturado)->setParameter("anioFact",$anioFacturado)->getResult();
            $fechaVencimiento = new \DateTime($fechaVenc[0][1]);

            $sqlSubConsultaPagosPeriodosAnteriores = "Select pagos2.fecha_consignacion FROM pagos as pagos2
            INNER JOIN facturas as facturas2 ON pagos2.id_factura = facturas2.id_factura
            WHERE facturas2.mes_facturado = ".$mesFacturado." and facturas2.anio_facturado = ".$anioFacturado."
            AND pagos2.is_deleted = 0
            AND facturas2.id_empresa = ".$ESAUsuario[0]->getIdEmpresa()->getIdEmpresa()." and pagos2.fecha_consignacion is not null
            AND DATE(pagos2.fecha_consignacion) BETWEEN '".$anioFacturado."-".($mesFacturado+1)."-01' AND '".$fechaVencimiento->format("Y-m-d")."'";

            $sqlPagosPeridosAnteriores = "
              SELECT
              pagos.vlr_pago, pagos.saldo, pagos.fecha_hora_pago,
              pagos.banco, pagos.fecha_consignacion, pagos.nro_consignacion,
              pagos.nro_cheque, pagos.observaciones, facturas.nro_factura,
              facturas.matricula, facturas.nombre_usuario, facturas.valor_factura,
              facturas.mes_facturado, facturas.anio_facturado, facturas.id_empresa, transacciones.nro_transaccion,
              transacciones.fecha_hora_transaccion, transacciones.total_transaccion, usuarios.nombre_completo,
              tipo_pagos.tipo_pago, metodos_pago.metodo_pago, sedes_agencias.nombre_sede, divipola.nom_poblad, empresas.razon_social
              FROM pagos
              INNER JOIN facturas ON pagos.id_factura = facturas.id_factura
              INNER JOIN transacciones ON pagos.id_transaccion = transacciones.id_transaccion
              INNER JOIN usuarios ON transacciones.id_usuario = usuarios.id_usuario
              INNER JOIN tipo_pagos ON pagos.id_tipo_pago = tipo_pagos.id_tipo_pago
              INNER JOIN metodos_pago ON pagos.id_metodo_pago = metodos_pago.id_metodo_pago
              INNER JOIN sedes_agencias ON usuarios.id_sede_agencia = sedes_agencias.id_sede_agencia
              INNER JOIN divipola ON sedes_agencias.id_divipola = divipola.divipola
              INNER JOIN empresas ON facturas.id_empresa = empresas.id_empresa
              WHERE facturas.mes_facturado = ".$mesFacturado." and facturas.anio_facturado = ".$anioFacturado."
              AND pagos.is_deleted = 0
              AND empresas.id_empresa = ".$ESAUsuario[0]->getIdEmpresa()->getIdEmpresa()."
              AND pagos.fecha_consignacion is not null
              AND DATE(pagos.fecha_consignacion) not in (".$sqlSubConsultaPagosPeriodosAnteriores.")
              GROUP By pagos.id_pago ORDER By pagos.fecha_hora_pago DESC
            ";
            $pagosPeriodosAnteriores = $mainService->rawQueryDoctrine($sqlPagosPeridosAnteriores);

            if(count($pagosPeriodosAnteriores) > 0){
              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Select",
                "tabla"=>"Pagos,Facturas",
                "id_datos"=>"vista informe pagos de periodos anteriores - filtrado y se generó el informe .".$exportType,
                "data"=>[$mesFacturado,$anioFacturado,$exportType]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              if($exportType == 'csv'){
                $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);
                $output = [];
                foreach ($pagosPeriodosAnteriores as $key => $value) {
                  if($session->get('rol') == 'Superusuario'){
                    $output[$key]["Empresa"] = $value["razon_social"];
                  }
                  $output[$key]["Sede Agencia"] = $value["nombre_sede"];
                  $output[$key]["Municipio"] = $value["nom_poblad"];
                  $output[$key]["Cajero"] = $value["nombre_completo"];
                  $output[$key]["NIU"] = $value["matricula"];
                  $output[$key]["Factura"] = $value["nro_factura"];
                  $output[$key]["Usuario"] = $value["nombre_usuario"];
                  $output[$key]["Mes/Año Facturado"] = $value["mes_facturado"]." - ".$value["anio_facturado"];
                  // $output[$key]["Transacción"] = $value["nro_transaccion"];
                  $output[$key]["Fecha / Hora Pago"] = $value["fecha_hora_pago"];
                  $output[$key]["Valor Pago"] = $value["vlr_pago"];
                  // $output[$key]["Saldo"] = $value["saldo"];
                  $output[$key]["Método Pago"] = $value["metodo_pago"];
                  // $output[$key]["Tipo Pago"] = $value["tipo_pago"];
                  $output[$key]["Banco"] = $value["banco"];
                  $output[$key]["Fecha Consignación o de Operación"] = $value["fecha_consignacion"];
                  $output[$key]["Nro. Consignación o de la Operación"] = $value["nro_consignacion"];
                  // $output[$key]["Nro. Cheque"] = $value["nro_cheque"];
                  $output[$key]["Observaciones"] = $value["observaciones"];
                }
                $outputCSV = $serializer->encode($output, 'csv');
                return new Response(
                    $outputCSV,
                    200,
                    array(
                        'Content-Type'          => 'text/csv; charset=utf-8',
                        'Content-Disposition'   => 'attachment; filename="InformePagosPeriodosAnteriores.csv"'
                    )
                );
              }else if($exportType == 'pdf'){
                //Exporta el informe.
                return $this->ExportInforme($pagosPeriodosAnteriores,"pdf");
              }else if($exportType == 'xls'){
                //Exporta el informe.
                return $this->ExportInforme($pagosPeriodosAnteriores,"excel");
              }else if($exportType == 'grafica'){
                //Se agregan variables al request.
                $request->query->set("mes",$mesFacturado);//Agrega un variable al request.
                $request->query->set("anio",$anioFacturado);//Agrega un variable al request.
                $request->query->set("cartera","true");//Agrega un variable al request.
                if($exportType == 'grafica' || $tipoInforme == 1){
                  $request->query->set("isAjax","true");//Agrega un variable al request.
                }

                //Pagos Periodos Anteriores.
                $pagosAnteriores = 0;
                foreach ($pagosPeriodosAnteriores as $key => $value) {
                  $pagosAnteriores += $value["vlr_pago"];
                }

                $getPagos = json_decode($mainService->getPagosAction($request)->getContent(),true);

                if(isset($getPagos) && $getPagos["status"] == 1){
                  $pagosTotal = $getPagos["pagosEmpresas"][$ESAUsuario[0]->getIdEmpresa()->getRazonSocial()]["PagosGeneral"];
                  $totalFacturado = $getPagos["pagosEmpresas"][$ESAUsuario[0]->getIdEmpresa()->getRazonSocial()]["totalFacturado"];
                  $porcPagosAnteriores = (($pagosAnteriores / $totalFacturado)*100);
                  $porcRecaudo = (($pagosTotal / $totalFacturado)*100);

                  return $this->render('@AppBundle/Informes/InformePagosPeriodosAnteriores.html.twig', array(
                      "form" => $form->createView(),
                      "porcentajePagosAnteriores" => round($porcPagosAnteriores,2),
                      "porcentajePagos" => round($porcRecaudo,2),
                      //"totalRecaudoGeneralCartera"=>$cartera["totalRecaudoGeneralCartera"],
                      "totalRecaudoGeneral"=>$pagosTotal,
                      "totalFacturado"=>$totalFacturado,
                      //"totalCartera"=>$cartera["totalCartera"]
                  ));
                }
              }
            }else{
              //Retornamos a la vista del informe.
              return $this->render('@AppBundle/Informes/InformePagosPeriodosAnteriores.html.twig', array(
                  "form" => $form->createView(),
                  "mensajes" => "No hay registros de pagos de periodos anteriores en el sistema dentro de los filtros seleccionados."
              ));
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Informe Pagos Periodos Anteriores",
            "id_datos"=>"vista informe pagos periodos anteriores",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Informes/InformePagosPeriodosAnteriores.html.twig', array(
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

          //Pagos Periodos Anteriores.
          $pagosAnteriores = 0;
          foreach ($data as $key => $value) {
            $pagosAnteriores += $value["vlr_pago"];
          }

          $html = $this->renderView("@AppBundle/Informes/PDFInformePagosPeriodosAnteriores.html.twig",
             [
               "config"=>$conf,
               "pagosAnteriores"=>$data,
               "totalPagosAnteriores"=>$pagosAnteriores,
               "typeExport"=>$typeExport,
               "base_dir" => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR
             ]
          );

          if($typeExport == 'pdf'){
              //Accede al servicio knp_snappy
               $snappy = $this->get('knp_snappy.pdf');
               $snappy->setTimeout(10000);
               $snappy->setOption("margin-bottom","13");

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
                       'Content-Disposition'   => 'attachment; filename="InformePagosPeriodosAnteriores.pdf"'
                   )
               );
           }else if($typeExport == 'excel'){
              return new Response(
                  $html,
                  200,
                  array(
                      'Content-Type'          => 'text/vnd.ms-excel; charset=utf-8',
                      'Content-Disposition'   => 'attachment; filename="InformePagosPeriodosAnteriores.xls"'
                  )
              );
            }else if($typeExport == 'view'){
                return $this->render('@AppBundle/Informes/PDFInformePagosPeriodosAnteriores.html.twig', array(
                      "config"=>$conf,
                      "pagosAnteriores"=>$data,
                      "totalPagosAnteriores"=>$pagosAnteriores,
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
