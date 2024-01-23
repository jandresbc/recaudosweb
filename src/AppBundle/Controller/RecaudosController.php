<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

///Para Decode Encode CSV
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

use AppBundle\Entity\Cajas;

class RecaudosController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $mainService = $this->get("main");

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('recaudos');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);

          if($session->get('rol') != 'Cajero' &&  $session->get('rol') != 'Inactivo'){
            if($session->get('rol') != 'Administrador'){
              $cajas = $em->getRepository("AppBundle:Cajas")
              ->createQueryBuilder("C")
              ->where("C.hasArchivado = 0")
              ->andWhere("C.idUsuario = :idUsu")
              ->setParameter("idUsu",$session->get('idUsuario'))
              ->orderBy('C.fechaHoraCreacion', 'DESC')
              ->getQuery()->getResult();
            }
          }else if($session->get('rol') == 'Cajero'){
            $usu = $mainService->getInfoUser($session->get('identificacion'),$em);

            $cajas = $em->getRepository("AppBundle:Cajas")
            ->createQueryBuilder("C")
            ->where("C.idUsuario = :idUsu")
            ->andWhere("C.hasArchivado = 0")
            ->setParameter("idUsu",$session->get('idUsuario'))
            ->orderBy('C.fechaHoraCreacion', 'DESC')
            ->getQuery()->getResult();
          }

          $mensajes = $request->query->get('mensajes');

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Recaudos",
            "id_datos"=>"vista recaudos",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          if($mensajes != '' && $mensajes != null){
            return $this->render('@AppBundle/recaudos/recaudos.html.twig', array(
                "cajas" => $cajas,
                "mensajes" => $mensajes
            ));
          }else{
            return $this->render('@AppBundle/recaudos/recaudos.html.twig', array(
                "cajas" => $cajas
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

    public function subirPagosAction(Request $request, Cajas $idCaja)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')->validarPermiso('subir pagos');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $fechaActual = new \DateTime("now",new \DateTimeZone('America/Bogota'));

          $cierres = $em->getRepository("AppBundle:Transacciones")
          ->createQueryBuilder("T")
          ->join("AppBundle:Cajas","C","WITH","T.idCaja = C.idCaja")
          ->where("T.isClosed = 0")
          ->andWhere("DATE(T.fechaHoraTransaccion) < :fechaActual")
          ->andWhere("T.idUsuario = ".$session->get('idUsuario'))
          ->setParameter("fechaActual",$fechaActual->format("Y-m-d"))
          ->getQuery()->getResult();

          $caja = $em->getRepository("AppBundle:Cajas")
          ->findBy(array("idCaja"=>$idCaja));

          if(count($cierres) == 0){
            $now = new \DateTime('now',new \DateTimeZone("America/Bogota"));
            $namespace = str_replace("\\","/",__NAMESPACE__);
            $base_url = str_replace("src/".$namespace,"",__DIR__);
            $base = explode("?",$_SERVER['HTTP_REFERER']);

            if($this->container->getParameter('kernel.environment') == "dev"){
              $urlabsolute = str_replace("/web/app_dev.php".$_SERVER['PATH_INFO'],"/",$base[0]);
            }else if($this->container->getParameter('kernel.environment') == "dev"){
              $urlabsolute = str_replace("/web".$_SERVER['PATH_INFO'],"/",$base[0]);
            }
            $filelog = $base_url."var/logs/ErrorPagosMasivos".$session->get("idUsuario").".log";
            $urllog = $urlabsolute."var/logs/ErrorPagosMasivos".$session->get("idUsuario").".log";
            $mensajes = "";

            //Elimina el archivo al cargar la vista de subir pagos.
            if(is_null($request->query->get("mensajes")) && file_exists($filelog)){
              unlink($filelog);
            }

            //Se crea un formulario, con el cual se sube el archivo csv.
            $form = $this->createFormBuilder()
            ->add("archivo",FileType::class,array(
              "label"=>"Seleccione Archivo:",
              "attr"=>array("class"=>"form-control btn btn-success btn-sm w-100","placeholder"=>"Elija archivo .csv")
            ))->getForm();

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
              $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);
              $logs = [];

              $file = $request->files->get('form')['archivo'];
              $extension = $file->getClientOriginalExtension();
              $filedatacsv = $form['archivo']->getData();
              $fileSize = $file->getClientSize();
              $fileRows = null; //Almacena los registros que se subieron o ajustaron en la facturación.

              if($fileSize <= 1000000){ // Menor o igual a 1 Mb.
                if($extension == 'csv'){
                  // decoding CSV contents
                  $data = $serializer->decode(file_get_contents($filedatacsv), 'csv');

                  $mensajes = "Se subió y/o procesó los pagos corréctamente.";
                  $response = $this->SerializedAndExecuted($request,$data,$idCaja->getIdCaja());//Ejecutar la inserción;

                  $error = [];
                  $done = [];
                  $lenData = count($data);
                  foreach ($response as $key => $value) {
                    if($key == 0){
                      if(file_exists($filelog)){
                        unlink($filelog);//Elimino el archivo.
                      }
                    }
                    if(isset($value["error"]) && $value["error"] != ''){
                      array_push($error,$value["error"]);
                      //crea un archivo con el logs de errores encontrados al subir los pagos.
                      file_put_contents($filelog,"[".$now->format("d/m/Y h:i:s a")."] ".$value["error"]."\n",FILE_APPEND);
                    }else if(isset($value["Done"])){
                      array_push($done,$value);
                    }
                  }

                  //Agrega un mensaje flash para mostrar los errores en pantalla.
                  if(count($error) > 0){
                    $this->addFlash("error",$error);
                    $this->addFlash("logfile",$urllog);
                  }

                  if(count($error) < $lenData){
                    //Activa la session de la caja.
                    //Modifica la ha activa la session de la caja.
                    if($caja[0]->getSessionActiva() == 0){
                      $caja[0]->setSessionActiva(1);
                      //Realiza los cambios.
                      $em->flush($caja[0]);

                      //Auditoria agrega un bloque a la cadena.
                      $dataAudCaja = [
                        "accion"=>"Update",
                        "tabla"=>"cajas",
                        "id_datos"=>$caja[0]->getIdCaja(),
                        "data"=>["session_activa = 1"]
                      ];

                      $blockchain->addBlock(new Block($dataAudCaja));
                      $blockchain->registerChain();
                    }
                    //Fin activación de la caja.

                    return $this->redirectToRoute('subirPagos',[
                      "idCaja"=>$idCaja->getIdCaja(),
                      "mensajes"=> $mensajes
                    ]);
                  }else{
                    return $this->redirectToRoute('subirPagos',[
                      "idCaja"=>$idCaja->getIdCaja()
                    ]);
                  }
                }else{
                  return $this->render('@AppBundle/recaudos/subirPagos.html.twig', [
                      "form" => $form->createView(),
                      'mensajes' => "El tipo de archivo cargado no está permitido. Archivo Encontrado .".$extension." tipo de archivo requerido .csv.",
                      "caja" => $idCaja
                  ]);
                }
              }else{
                return $this->render('@AppBundle/recaudos/subirPagos.html.twig', [
                    "form" => $form->createView(),
                    'mensajes' => "El archivo cargado supera el tamaño permitido (1 Mb).",
                    "caja" => $idCaja
                ]);
              }
            }

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entró al módulo de Subir Pagos Masivos",
              "id_datos"=>"vista subir pagos masivos",
              "data"=>["cargó vista"]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            $error = $session->getFlashBag()->get("error");
            $log = $session->getFlashBag()->get("logfile");

            return $this->render('@AppBundle/recaudos/subirPagos.html.twig', [
                "form" => $form->createView(),
                "mensajes" => $mensajes != '' ? $mensajes : null,
                "Error" => count($error) > 0 ? $error : null,
                "Log" => $log != '' ? $log : null,
                "caja" => $idCaja
            ]);
          }else if(count($cierres) > 0){
            $cajas = $em->getRepository("AppBundle:Cajas")
            ->createQueryBuilder("C")
            ->where("C.idUsuario = :idUsu")
            ->andWhere("C.hasArchivado = 0")
            ->setParameter("idUsu",$session->get('idUsuario'))
            ->getQuery()->getResult();

            //Auditoria agrega un bloque a la cadena.
            $dataAudView = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Recaudos",
              "id_datos"=>"vista de recaudos",
              "data"=>["cargó vista","mensajes"=>"No puede Entrar a Subir Pagos porque tiene transacciones anteriores registradas, sin hacer su cierre de caja. Por favor realice el cierre de caja antes, para poder ingresar al módulo de recaudos."]
            ];

            $blockchain->addBlock(new Block($dataAudView));
            $blockchain->registerChain();

            return $this->render('@AppBundle/recaudos/recaudos.html.twig', array(
                "cajas" => $cajas,
                "mensajes" => "No puede Entrar a Subir Pagos porque tiene transacciones anteriores registradas, sin hacer su cierre de caja. Por favor realice el cierre de caja antes, para poder ingresar al módulo de recaudos."
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

    public function SerializedAndExecuted($request,$data,$idCaja){
      $session = new Session();
      $main = $this->get("main");
      $return = [];

      foreach ($data as $key => $value) {
        try{
          $requestContent = [];
          $requestContent = array_merge(["idCajaActual"=>$idCaja],$requestContent);
          $requestContent = array_merge(["pagosMasivos"=>true],$requestContent);
          $request->query->set("valorFactura",$value["Valor recaudado"]);
          $datosFactura = $main->getFacturaAction($request,$value["Referencia"],$idCaja);
          $datosFactura = json_decode($datosFactura->getContent(),true);

          if (strtolower($value["Medio de pago"]) == 'cheque'){
            if ($value["Nro de Operación"] == ''){
              array_push($return,["status"=>0,"error"=>"No se puede registrar el pago de la factura con Referencia: ".$value["Referencia"].", porque el medio de pago es: ".$value["Medio de pago"]." y es necesario que se ingrese un Número de Operación(Número Cheque)."]);
            }
          }

          if(!isset($datosFactura["status"])){
            foreach ($datosFactura as $keyFact => $factura) {
              $fechaTransaccion = new \DateTime("now",new \DateTimeZone("America/Bogota"));
              $pagoRegistrado = $main->rawQueryDoctrine("Select vlr_pago,fecha_hora_pago From pagos where pagos.id_factura = (Select id_factura From facturas where facturas.nro_factura=".$factura["nroFactura"]." and facturas.valor_factura = ".$factura["valorFactura"].") limit 1");

              if(count($pagoRegistrado) == 0){
                //Datos para registrar la transacción.
                $requestContent = array_merge(["fechaTransaccion"=>$fechaTransaccion->format("Y-m-d H:i:s")],$requestContent);
                $observaciones = $value["Observaciones"] != '' ? "Pago registrado masívamente por el módulo de 'Subir Pagos'. Fecha de Registro: ".$fechaTransaccion->format("d/m/Y H:i:s")."\n Observaciones del pago: ".$value['Observaciones'] : "Pago registrado masívamente por el módulo de 'Subir Pagos'. Fecha de Registro: ".$fechaTransaccion->format("d/m/Y H:i:s");
                $requestContent = array_merge([
                  "threads"=>[
                    "pagos"=>[
                      "totalAPagar"=>$factura["valorFactura"],
                      "banco"=>ucwords($value["Entidad Financiera Recaudadora"]),
                      "fechaConsignacion"=>$value["Fecha del Recaudo"],
                      "nroConsignacion"=>strtolower($value["Medio de pago"]) != 'cheque' ? $value["Nro de Operación"] : "",
                      "observaciones"=>$observaciones,
                      "nroCheque"=>strtolower($value["Medio de pago"]) == 'cheque' ? $value["Nro de Operación"] : "",
                      "metodoPago"=>$value["Medio de pago"],
                      "facturas"=>[0=>[
                        "nroFactura"=>$factura["nroFactura"],
                        "valorFactura"=>$factura["valorFactura"]
                      ]]
                    ]
                  ]
                ],$requestContent);

                $request->initialize(
                    $request->query->all(),
                    $request->request->all(),
                    $request->attributes->all(),
                    $request->cookies->all(),
                    $request->files->all(),
                    $request->server->all(),
                    json_encode($requestContent)
                );

                $pagar = $main->saveTransactionAction($request);
                $result = json_decode($pagar->getContent(),true);

                if($result["status"] == "Done"){
                  array_push($return,["status"=>$result["status"],"Referencia"=>$result["pagos"][0]["nroFactura"],"codigoTransaccion"=>$result["pagos"][0]["codigoTransaccion"]]);
                }

              }else{
                array_push($return,["status"=>0,"error"=>"No se puede registrar el pago de la factura con referencia: ".$value["Referencia"].", porque ya se encuentra registrado un pago en el sistema."]);
              }
            }
          }else{
            $datosFactura["error"] = "Error en el Registro con Referencia: ".$value["Referencia"].". ".$datosFactura["error"];
            array_push($return,$datosFactura);
          }

          $request->query->remove("valorFactura");

        }catch(\Exception $er){
            array_push($return,["error"=>"Error al procesar los pagos. ".$er->getMessage()]);
            // return $this->redirectToRoute("subirPagos",['idCaja'=>$idCaja]);
            // return $this->redirect($this->generateUrl('subirPagos',['idCaja'=>$idCaja]));
        }
      }

      return $return;
    }

    public function historialCajasAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('historial cajas');

        if($permiso === true){
          //Paginador
          $paginator = $this->get('knp_paginator');
          $GLOBALS['empresasFacturacion']=[];
          //Auditoria
          $blockchain = new Blockchain($em);

          $form = $this->createFormBuilder()
          ->add("nombreCaja",TextType::class,array(
            "label"=>"Nombre Caja:",
            "required"=>false,
            "attr"=>array("class"=>"form-control w-100","placeholder"=>"Nombre Caja")
          ))
          ->add("archivado",ChoiceType::class,array(
            "placeholder"=>"Seleccione",
            "required"=>false,
            "attr"=>["class"=>"form-control w-100 select2"],
            "choices"=>[
              "Cajas Archivadas" => 1,
              "Cajas sin Archivar" => 0
              ]
          ))
          ->add("session",ChoiceType::class,array(
            "placeholder"=>"Seleccione",
            "required"=>false,
            "attr"=>["class"=>"form-control w-100 select2"],
            "choices"=>[
              "Cajas con Sesión Iniciada" => 1,
              "Cajas sin Inicar Sesión" => 0
              ]
          ))->getForm();

          $form->handleRequest($request);

          if($session->get('rol') != 'Cajero' &&  $session->get('rol') != 'Inactivo'){
            $idemp = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$session->get("idSedeAgencia")]);

            $cajas = $em->getRepository("AppBundle:Cajas")
            ->createQueryBuilder("C")
            ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","C.idEmpresaSedeAgencia=ESA.idEmpresaSedeAgencia");

            foreach ($idemp as $key => $value) {
              $cajas->orWhere("ESA.idEmpresa = :idEmp".$key)
              ->setParameter("idEmp".$key,$value->getIdEmpresa());
            }

            $cajas->orderBy('C.fechaHoraCreacion', 'DESC');
          }else if($session->get('rol') == 'Cajero'){
            $cajas = $em->getRepository("AppBundle:Cajas")
            ->createQueryBuilder("C")
            ->where("C.idUsuario = :idUsu")
            ->setParameter("idUsu",$session->get('idUsuario'))
            ->orderBy('C.fechaHoraCreacion', 'DESC');
          }

          if ($form->isSubmitted() && $form->isValid()) {
            //Filtros de busqueda en el listado.
            $nombreCaja = $request->request->get('form')['nombreCaja'];
            $archivado = $request->request->get('form')['archivado'];
            $sesion = $request->request->get('form')['session'];

            if($nombreCaja != '' && $nombreCaja != null){
              $cajas->andWhere("C.nombreCaja like :nomb")
              ->setParameter("nomb","%".$nombreCaja."%");
            }

            if($archivado != '' && $archivado != null){
              $cajas->andWhere("C.hasArchivado = :has")
              ->setParameter("has",$archivado);
            }

            if($sesion != '' && $sesion != null){
              $cajas->andWhere("C.sessionActiva = :ses")
              ->setParameter("ses",$sesion);
            }
            //Fin filtros.

            $historial = $paginator->paginate(
                    $cajas->getQuery(),
                    $request->query->getInt('page', 1),
                    10
            );

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Historial Cajas",
              "id_datos"=>"vista historial cajas - Lista Filtrada",
              "data"=>["cargó vista filtrada"]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            return $this->render('@AppBundle/recaudos/historialCajas.html.twig', array(
                'historial' => $historial,
                'form' => $form->createView()
            ));
          }

          $historial = $paginator->paginate(
                  $cajas->getQuery(),
                  $request->query->getInt('page', 1),
                  10
          );

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Historial Cajas",
            "id_datos"=>"vista historial cajas - Listar",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/recaudos/historialCajas.html.twig', array(
              'historial' => $historial,
              'form' => $form->createView()
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
