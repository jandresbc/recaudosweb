<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class InformeTotalRecaudadoController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        ini_set("max_execution_time", "1200");//20 Min.
        ini_set('memory_limit', '1024M');
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('informe total recaudado');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createForm("AppBundle\Form\InformeTotalRecaudadoType");
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);

            $Municipio = $request->request->get('appbundle_informetotalreaudado')['Municipio'];
            $Agencia = $request->request->get('appbundle_informetotalreaudado')['Agencias'];
            $sedeAgencia = $request->request->get('appbundle_informetotalreaudado')['sedesAgencias'];
            $fechaInicio = $request->request->get('appbundle_informetotalreaudado')['fechaInicio'];
            $fechaFinal = $request->request->get('appbundle_informetotalreaudado')['fechaFin'];
            $exportType = $request->request->get('exportType');

            //Se Valida que se seleccione algún filtro.
            if($Agencia == "" && $sedeAgencia == '' && $fechaInicio == "" && $fechaFinal == "" && $Municipio == ""){
              $mensajes = "No ha seleccionado ninguno de los filtros, por favor seleccione alguno para obtener los resultados deseados.";
              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Load",
                "tabla"=>"Entró al módulo de Informe Total Recaudado",
                "id_datos"=>"vista informe total recaudado - filtrado.",
                "data"=>[$mensajes]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              //Retornamos a la vista del informe.
              return $this->render('@AppBundle/Informes/InformeTotalRecaudado.html.twig', array(
                  "form" => $form->createView(),
                  "mensajes" => $mensajes
              ));
            }

            $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
            ->findBy(["idSedeAgencia"=>$session->get('idSedeAgencia')]);

            $sql = "SELECT
            pagos.vlr_pago,
            pagos.fecha_hora_pago,
            pagos.banco,
            pagos.fecha_consignacion,
            pagos.nro_consignacion,
            pagos.nro_cheque,
            pagos.observaciones,
            transacciones.nro_transaccion,
            usuarios.nombre_completo,
            usuarios.identificacion,
            facturas.nro_factura,
            facturas.matricula,
            facturas.nombre_usuario,
            facturas.concepto,
            divipola.nom_poblad,
            sedes_agencias.nombre_sede,
            agencias.nombre_agencia,
            empresas.razon_social,
            empresas.nit,
            divipola.nom_poblad,
            tipo_pagos.tipo_pago,
            metodos_pago.metodo_pago
            FROM
            pagos
            INNER JOIN transacciones ON pagos.id_transaccion = transacciones.id_transaccion
            INNER JOIN facturas ON pagos.id_factura = facturas.id_factura
            INNER JOIN empresas ON facturas.id_empresa = empresas.id_empresa
            INNER JOIN empresas_sedes_agencias ON transacciones.id_empresa_sede_agencia = empresas_sedes_agencias.id_empresa_sede_agencia
            INNER JOIN sedes_agencias ON empresas_sedes_agencias.id_sede_agencia = sedes_agencias.id_sede_agencia
            INNER JOIN agencias ON sedes_agencias.id_agencia = agencias.id_agencia
            INNER JOIN usuarios ON transacciones.id_usuario = usuarios.id_usuario
            INNER JOIN cajas ON usuarios.id_usuario = cajas.id_usuario
            INNER JOIN divipola ON sedes_agencias.id_divipola = divipola.divipola
            INNER JOIN tipo_pagos ON pagos.id_tipo_pago = tipo_pagos.id_tipo_pago
            INNER JOIN metodos_pago ON pagos.id_metodo_pago = metodos_pago.id_metodo_pago
            WHERE pagos.is_deleted = 0 and ";

            if($session->get('rol') == 'Superusuario' ){
              foreach ($ESAUsuario as $key => $value) {
                if($key == 0){
                  $sql .= " empresas_sedes_agencias.id_empresa = ".$value->getIdEmpresa()->getIdEmpresa();
                }else{
                  $sql .= " OR empresas_sedes_agencias.id_empresa = ".$value->getIdEmpresa()->getIdEmpresa();
                }
              }
            }elseif($session->get('rol') == 'Administrador' || $session->get('rol') == "Auditor"){
              $sql .= " empresas_sedes_agencias.id_empresa = ".$ESAUsuario[0]->getIdEmpresa()->getIdEmpresa();
            }else if($session->get('rol') == 'Administrador Agencias'){
              $sql .= " agencias.id_agencia = ".$ESAUsuario[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia();
              $sql .= " AND empresas_sedes_agencias.id_empresa = ".$ESAUsuario[0]->getIdEmpresa()->getIdEmpresa();
            }else if($session->get('rol') == 'Cajero'){
              $sql .= " AND sedes_agencias.id_sede_agencia = ".$session->get("idSedeAgencia");

              foreach ($ESAUsuario as $key => $value) {
                if($key == 0){
                  $sql .= " AND empresas_sedes_agencias.id_empresa = ".$value->getIdEmpresa()->getIdEmpresa();
                }else{
                  $sql .= " OR empresas_sedes_agencias.id_empresa = ".$value->getIdEmpresa()->getIdEmpresa();
                }
              }
            }

            //Filtros del Informe.
            if($Municipio != null && $Municipio != ''){
              $sql .= " AND sedes_agencias.id_divipola = '".$Municipio."'";
            }

            if($Agencia != null && $Agencia != ''){
              $sql .= " AND agencias.id_agencia = '".$Agencia."'";
            }

            if($sedeAgencia != null && $sedeAgencia != ''){
              $sql .= " AND transacciones.id_empresa_sede_agencia = '".$sedeAgencia."'";
            }

            if($fechaInicio != null && $fechaInicio != ''){
              $sql .= " AND DATE(pagos.fecha_hora_pago) >= '".$fechaInicio."'";

              if($fechaFinal == null || $fechaFinal == ''){
                $sql .= " AND DATE(pagos.fecha_hora_pago) <= '".new \DateTime('now')."'";
              }
            }

            if($fechaFinal != null && $fechaFinal != ''){
              $sql .= " AND DATE(pagos.fecha_hora_pago) <= '".$fechaFinal."'";

              if($fechaInicio == null || $fechaInicio == ''){
                $sql .= " AND DATE(pagos.fecha_hora_pago) >= '".new \DateTime('now')."'";
              }
            }
            //Fin Filtros.

            $sql .= " GROUP By pagos.id_pago ORDER By pagos.fecha_hora_pago DESC";

            $pagos = $mainService->rawQueryDoctrine($sql);

            //Cálculo del totalRecaudado.
            $totalRecaudado = 0;
            foreach ($pagos as $key => $value) {
              $totalRecaudado += $value["vlr_pago"];
            }

            if(count($pagos) > 0){
              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Select",
                "tabla"=>"Pagos",
                "id_datos"=>"vista informe total recaudado - filtrado y se generó el informe .".$exportType,
                "data"=>[$Municipio,$Agencia,$sedeAgencia,$fechaInicio,$fechaFinal,$exportType]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              if($exportType == 'pdf'){
                //Exporta el informe.
                return $this->ExportInforme($pagos,$totalRecaudado,"pdf");
              }else if($exportType == 'xls'){
                //Exporta el informe.
                return $this->ExportInforme($pagos,$totalRecaudado,"excel");
              }else if($exportType == 'csv'){
                $output = [];
                foreach ($pagos as $key => $value) {
                  $fecha = new \DateTime($value["fecha_hora_pago"]);
                  $output[$key]["Transaccion"] = $value["nro_transaccion"];
                  $output[$key]["Cajero"] = $value["nombre_completo"];
                  if(array_search($session->get('nitAgencia'),$session->get('nitEmpresas')) === false){
                    $output[$key]["Identificacion"] = $value["identificacion"];
                  }
                  if($session->get('rol') == 'Superusuario'){
                    $output[$key]["Empresa/Sede Agencia"] =  $value["razon_social"]." | ".$value["nombre_sede"];
                  }else{
                      $output[$key]["Sede Agencia"] = $value["nombre_sede"];
                  }
                  $output[$key]["Municipio"] = $value["nom_poblad"];
                  $output[$key]["NIU"] = $value["matricula"];
                  $output[$key]["Factura"] = $value["nro_factura"];
                  $output[$key]["Concepto"] = $value["concepto"];
                  $output[$key]["Usuario"] = $value["nombre_usuario"];
                  $output[$key]["Fecha / Hora Pago"] = $fecha->format("d/m/Y h:i:s a");
                  $output[$key]["Valor Pago"] = $value["vlr_pago"];
                  $output[$key]["Metodo Pago"] = $value["metodo_pago"];
                  $output[$key]["Tipo Pago"] = $value["tipo_pago"];
                  $output[$key]["Banco"] = $value["banco"];
                  $output[$key]["Nro./Fecha Consignacion"] = $value["nro_consignacion"];
                  // $output[$key]["Nro. Cheque"] = $value["nro_cheque"];
                  $output[$key]["Observaciones"] = $value["observaciones"];
                }
                $outputCSV = $serializer->encode($output, 'csv');
                return new Response(
                    $outputCSV,
                    200,
                    array(
                        'Content-Type'          => 'text/csv; charset=utf-8',
                        'Content-Disposition'   => 'attachment; filename="InformeTotalRecaudado.csv"'
                    )
                );
              }
            }else{
                //Retornamos a la vista del informe.
                return $this->render('@AppBundle/Informes/InformeTotalRecaudado.html.twig', array(
                    "form" => $form->createView(),
                    "mensajes" => "No hay pagos registrados en el sistema dentro de los filtros seleccionados."
                ));
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Informe Total Recaudado",
            "id_datos"=>"vista informe total recaudado",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Informes/InformeTotalRecaudado.html.twig', array(
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
          $html = $this->renderView("@AppBundle/Informes/PDFInformeTotalRecaudado.html.twig",
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

               return new PdfResponse(
                   $snappy->getOutputFromHtml($html,
                     array(
                         'orientation' => 'Landscape',
                         'images' => true,
                         'enable-javascript' => true
                     )
                   ),
                   'informeTotalRecaudado.pdf'
               );
           }else if($typeExport == 'excel'){
              return new Response(
                  $html,
                  200,
                  array(
                      'Content-Type'          => 'text/vnd.ms-excel; charset=utf-8',
                      'Content-Disposition'   => 'attachment; filename="informeTotalRecaudado.xls"'
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
          return $this->redirectToRoute('InformeTotalRecaudo', array(
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
