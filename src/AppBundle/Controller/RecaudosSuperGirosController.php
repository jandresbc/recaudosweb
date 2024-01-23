<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class RecaudosSuperGirosController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('informe supergiros');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createFormBuilder()
          ->add("fechaInicial",DateType::class,[
            "label"=>"Desde *",
            "widget"=>"single_text",
            "attr"=>array("class"=>"form-control w-100")
          ])
          ->add("fechaFinal",DateType::class,[
            "label"=>"Hasta *",
            "widget"=>"single_text",
            "attr"=>["class"=>"form-control w-50"]
          ])->add("tipoDatos",ChoiceType::class,[
            "label"=>"Tipo Datos *",
            "attr"=>["class"=>"text-center radio"],
            "multiple"=>false,
            "expanded"=>true,
            "data"=>1,
            "choices"=>["Por Cierres de Caja"=>1,"Por Pagos Registrados"=>2]
          ])
          ->getForm();

          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);

            $fechaInicio = $request->request->get('form')['fechaInicial'];
            $fechaFinal = $request->request->get('form')['fechaFinal'];
            $tipoDatos = $request->request->get('form')['tipoDatos'];
            $exportType = $request->request->get('exportType');

            $esa = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy([
              "idSedeAgencia"=>$session->get("idSedeAgencia")
            ]);

            if($tipoDatos == 1){//Por cierres de caja.
              $cierres = $em->getRepository("AppBundle:CierresDeCajas")
              ->createQueryBuilder("CDC")
              ->join("AppBundle:CierresDeCajasTransacciones","CDCT","WITH","CDCT.idCierreDeCaja=CDC.idCierreCaja")
              ->join("AppBundle:Pagos","P","WITH","CDCT.idTransaccion=P.idTransaccion")
              ->join("AppBundle:Cajas","C","WITH","C.idCaja=CDC.idCaja")
              ->join("AppBundle:Usuarios","U","WITH","U.idUsuario=C.idUsuario")
              ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","C.idEmpresaSedeAgencia=ESA.idEmpresaSedeAgencia")
              ->join("AppBundle:SedesAgencias","SA","WITH","SA.idSedeAgencia=ESA.idSedeAgencia")
              ->join("AppBundle:Agencias","A","WITH","SA.idAgencia=A.idAgencia")
              ->join("AppBundle:Divipola","DV","WITH","SA.idDivipola=DV.divipola")
              ->where("A.idAgencia = :idAgen")
              ->andWhere("P.isDeleted = 0")
              ->setParameter("idAgen",$esa[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());

              if($fechaInicio != '' && $fechaFinal != ''){
                $cierres->andWhere("DATE(CDC.fechaHoraCierre) >= :inicio");
                $cierres->andWhere("DATE(CDC.fechaHoraCierre) <= :final");
                $cierres->setParameter("inicio",$fechaInicio);
                $cierres->setParameter("final",$fechaFinal);
              }

              $result = $cierres->getQuery()->getResult();
            }else if($tipoDatos == 2){//Por pagos registrados.
              $pagos = $em->getRepository("AppBundle:Pagos")
              ->createQueryBuilder("P")
              ->join("AppBundle:Transacciones","T","WITH","T.idTransaccion=P.idTransaccion")
              ->join("AppBundle:Cajas","C","WITH","C.idCaja=T.idCaja")
              ->join("AppBundle:Usuarios","U","WITH","U.idUsuario=C.idUsuario")
              ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","C.idEmpresaSedeAgencia=ESA.idEmpresaSedeAgencia")
              ->join("AppBundle:SedesAgencias","SA","WITH","SA.idSedeAgencia=ESA.idSedeAgencia")
              ->join("AppBundle:Agencias","A","WITH","SA.idAgencia=A.idAgencia")
              ->join("AppBundle:Divipola","DV","WITH","SA.idDivipola=DV.divipola")
              ->where("A.idAgencia = :idAgen")
              ->andWhere("P.isDeleted = 0")
              ->setParameter("idAgen",$esa[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());

              if($fechaInicio != '' && $fechaFinal != ''){
                $pagos->andWhere("DATE(P.fechaHoraPago) >= :inicio");
                $pagos->andWhere("DATE(P.fechaHoraPago) <= :final");
                $pagos->setParameter("inicio",$fechaInicio);
                $pagos->setParameter("final",$fechaFinal);
              }

              $result = $pagos->getQuery()->getResult();
            }

            if(count($result)>0){

              $template = $this->renderView('@AppBundle/Informes/SuperGiros/ExportInformeSupergiros.html.twig', array(
                  "data" => $result,
                  "typeExport"=>"excel"
              ));

              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Select",
                "tabla"=>"cierres_de_cajas",
                "id_datos"=>"vista informe supergiros - filtrado y se generó el informe .".$exportType,
                "data"=>[$fechaInicio,$fechaFinal,$tipoDatos,$exportType]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              if($exportType == 'excel'){
                return new Response(
                    $template,
                    200,
                    array(
                        'Content-Type'          => 'text/vnd.ms-excel; charset=utf-8',
                        'Content-Disposition'   => 'attachment; filename="InformeSuperGiros.xls"'
                    )
                );
              }else if($exportType == 'csv'){
                $output = [];
                foreach ($result as $key => $value) {
                  if($tipoDatos == 1){//Por cierres de caja.
                    array_push($output,[
                      "Nit"=>$value->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia()->getNitAgencia(),
                      "Entidad"=>$value->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia()->getNombreAgencia(),
                      "Zona"=>$value->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdDivipola()->getNomPoblad(),
                      "Oficina"=>$value->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getNombreSede(),
                      "Telefono"=>$value->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getTelCel(),
                      "Celular Ofic"=>$value->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getTelCel(),
                      "Recaudador"=>$value->getIdCaja()->getIdUsuario()->getNombreCompleto(),
                      "Nro Identi Recaudador"=>$value->getIdCaja()->getIdUsuario()->getIdentificacion(),
                      "Lote"=>$value->getNroDocumento(),
                      "Cupones"=>$value->getTotalColillas(),
                      "Total Lote"=>$value->getVlrEnCaja(),
                      "Fecha Recaudo / Cierre de Caja"=>$value->getFechaHoraCierre()->format("Y-m-d H:i:s"),
                      "Estado"=>"Recaudado",
                      "Entidad Bancaria"=>"",
                      "Municipio"=>$value->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdDivipola()->getNomPoblad(),
                      "Nro Cuenta"=>"",
                      "Tipo Cuenta"=>""
                    ]);
                  }else if($tipoDatos == 2){//Por pagos registrados.
                    array_push($output,[
                      "Nit"=>$value->getIdTransaccion()->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia()->getNitAgencia(),
                      "Entidad"=>$value->getIdTransaccion()->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia()->getNombreAgencia(),
                      "Zona"=>$value->getIdTransaccion()->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdDivipola()->getNomPoblad(),
                      "Oficina"=>$value->getIdTransaccion()->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getNombreSede(),
                      "Telefono"=>$value->getIdTransaccion()->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getTelCel(),
                      "Celular Ofic"=>$value->getIdTransaccion()->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getTelCel(),
                      "Recaudador"=>$value->getIdTransaccion()->getIdCaja()->getIdUsuario()->getNombreCompleto(),
                      "Nro Identi Recaudador"=>$value->getIdTransaccion()->getIdCaja()->getIdUsuario()->getIdentificacion(),
                      "Nro. Transaccion"=>$value->getIdTransaccion()->getNroTransaccion(),
                      "Cupones"=>"",
                      "Total Lote"=>$value->getVlrPago(),
                      "Fecha Recaudo"=>$value->getFechaHoraPago()->format("Y-m-d H:i:s"),
                      "Estado"=>"Recaudado",
                      "Entidad Bancaria"=>"",
                      "Municipio"=>$value->getIdTransaccion()->getIdCaja()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdDivipola()->getNomPoblad(),
                      "Nro Cuenta"=>"",
                      "Tipo Cuenta"=>""
                    ]);
                  }

                }
                $outputCSV = $serializer->encode($output, 'csv');
                return new Response(
                    $outputCSV,
                    200,
                    array(
                        'Content-Type'          => 'text/csv; charset=utf-8',
                        'Content-Disposition'   => 'attachment; filename="InformeSuperGiros.csv"'
                    )
                );
              }
            }else{
              $mensajes = "No hay cierres de caja registrados dentro del rango de fecha seleccionado para la Agencia SuperGiros S.A.";
              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Load",
                "tabla"=>"Entro al módulo de Informe SuperGiros",
                "id_datos"=>"vista informe supergiros",
                "data"=>["cargó vista",$mensajes]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();
              return $this->render('@AppBundle/Informes/SuperGiros/InformeSuperGiros.html.twig', array(
                  "form" => $form->createView(),
                  "mensajes" => $mensajes
              ));
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Informe SuperGiros",
            "id_datos"=>"vista informe supergiros",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Informes/SuperGiros/InformeSuperGiros.html.twig', array(
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
          "
        )->setParameter("idUs",$cajas[0]->getIdCaja()->getIdUsuario())
        ->setParameter("idCaja",$cajas[0]->getIdCaja()->getIdCaja())
        ->setParameter("idCierre",$idCierreCaja)
        ->getResult();

        if(count($cajas) > 0){
          $html = $this->renderView("@AppBundle/Informes/PDFInformeCierresDeCajas.html.twig",
             array(
               "cajas"=>$cajas,
               "transacciones" => $transacciones,
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
