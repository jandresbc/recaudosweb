<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\FileBag;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
///Para Decode Encode CSV
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

//Entities
use AppBundle\Entity\Facturas;

//Auditoria.
use AppBundle\Blockchain\Blockchain;
use AppBundle\Blockchain\Block;

/**
 * Factura controller.
 *
 */
class FacturasController extends Controller
{
    /**
     * Lists all factura entities.
     *
     */
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('facturas');

        if($permiso === true){
          //Paginador
          $paginator = $this->get('knp_paginator');
          $GLOBALS['empresasFacturacion']=[];
          //Auditoria
          $blockchain = new Blockchain($em);

          $form = $this->createFormBuilder()
          ->add("nroFactura",NumberType::class,array(
            "label"=>"Número Factura:",
            "required"=>false,
            "attr"=>array("class"=>"form-control w-100","placeholder"=>"Número de Factura")
          ))
          ->add("empresas",EntityType::class,array(
            "attr"=>["class"=>"form-control w-75 select2","placeholder"=>"Seleccione"],
            "class"=>"AppBundle:Empresas",
            "query_builder"=>function(EntityRepository $er){
              $session = new Session();
              $query = null;
              $em = $GLOBALS['kernel']->getContainer()
              ->get('doctrine')->getEntityManager();

              $ESA = $em->createQuery("
                SELECT ESA FROM
                AppBundle:EmpresasSedesAgencias ESA
                WHERE ESA.idSedeAgencia = :idSAgencia
              ")->setParameter("idSAgencia",$session->get('idSedeAgencia'))
              ->getResult();

              $query = $er->createQueryBuilder("E");

              if($session->get('rol') == "Administrador"){
                foreach ($ESA as $key => $value) {
                  $query->orWhere("E.idEmpresa = :idEmp".$key)
                  ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
                }
              }

              $GLOBALS['empresasFacturacion'] = $query->getQuery()->getResult();

              return $query;
            }
          ))
          ->add("niu",NumberType::class,array(
            "label"=>"NIU (matricula):",
            "required"=>false,
            "attr"=>array("class"=>"form-control w-100","placeholder"=>"NIU")
          ))->getForm();

          $form->handleRequest($request);

          $empresas = $GLOBALS['empresasFacturacion'];

          $facturas = $em->getRepository('AppBundle:Facturas')
          ->createQueryBuilder("F")
          ->orderBy("F.idFactura","DESC");

          if($session->get('rol') == "Administrador"){
            $facturas->where("F.idEmpresa = :idEmp")
            ->setParameter("idEmp",$empresas[0]->getIdEmpresa());
          }

          if ($form->isSubmitted() && $form->isValid()) {
            //Filtros de busqueda en el listado.
            $nroFactura = $request->request->get('form')['nroFactura'];
            $empresa = $request->request->get('form')['empresas'];
            $niu = $request->request->get('form')['niu'];

            if($nroFactura != '' && $nroFactura != null){
              $facturas->andWhere("F.nroFactura = :idFact")
              ->setParameter("idFact",$nroFactura);
            }

            if($niu != '' && $niu != null){
              $facturas->andWhere("F.matricula = :niu")
              ->setParameter("niu",$niu);
            }

            if($empresa != '' && $empresa != null){
              if($session->get('rol') == "Superusuario"){
                $facturas->where("F.idEmpresa = :idEmp");
              }
              $facturas->setParameter("idEmp",$empresa);
            }
            //Fin filtros.

            $facts = $paginator->paginate(
                    $facturas->getQuery(),
                    $request->query->getInt('page', 1),
                    20
            );

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Facturación",
              "id_datos"=>"vista facturación - Lista Filtrada",
              "data"=>["cargó vista filtrada"]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            return $this->render('facturas/index.html.twig', array(
                'facturas' => $facts,
                'form' => $form->createView()
            ));
          }

          $facts = $paginator->paginate(
                  $facturas->getQuery(),
                  $request->query->getInt('page', 1),
                  20
          );

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Facturación",
            "id_datos"=>"vista facturación - Listar",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('facturas/index.html.twig', array(
              'facturas' => $facts,
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

    public function finalizarRecaudoAction(Request $request){
      $session = $request->getSession();
      if($session->get('auth') == 1){
        ini_set("max_execution_time", "1200");//20 Min.
        ini_set('memory_limit', '1024M');
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('finalizar activar recaudos');

        if($permiso === true){
          $main = $this->get("main");
          //Auditoria.
          $blockchain = new Blockchain($em);
          $años = $this->getAniosPeriodos();

          $form = $this->createFormBuilder()
          ->add('mesFacturado',ChoiceType::class,array(
            "label"=>"Mes de Facturación: *",
            "required"=>true,
            "attr"=>array("class"=>"form-control w-100","data-placeholder"=>"Seleccione"),
            "placeholder"=>"Seleccione",
            "data"=>date("m")-1,
            "choices"=>[
              "Enero" => "1",
              "Febrero" => "2",
              "Marzo" => "3",
              "Abril" => "4",
              "Mayo" => "5",
              "Junio" => "6",
              "Julio" => "7",
              "Agosto" => "8",
              "Septiembre" => "9",
              "Octubre" => "10",
              "Noviembre" => "11",
              "Diciembre" => "12",
            ]
          ))->add('anioFacturado',ChoiceType::class,array(
            "label"=>"Año de Facturación: *",
            "required"=>true,
            "attr"=>array("class"=>"form-control w-100","data-placeholder"=>"Seleccione"),
            "placeholder"=>"Seleccione",
            'data' => date("Y"),
            "choices"=>$años
          ))
          ->add("fechaVencimiento",DateType::class,array(
            "label"=>"Fecha de Vencimiento:",
            "required"=>false,
            "widget"=>"single_text",
            "attr"=>array("class"=>"form-control w-100")
          ))->add("tipoProceso",ChoiceType::class,array(
            "label"=>"Tipo Proceso:",
            "required"=>true,
            "multiple"=>false,
            "expanded"=>true,
            "data"=>1,
            "choices"=>[
              "Activar Recaudo"=>1,
              "Finalizar Recaudo"=>2,
              "Modificar Fechas de Vencimiento" => 3
            ],
            "attr"=>array("class"=>"radio w-100")
          ))->getForm();

          $form->handleRequest($request);

          if ($form->isSubmitted() && $form->isValid()) {
            $tipoProceso = $request->request->get("form")['tipoProceso'];
            $mesFacturado = $request->request->get("form")['mesFacturado'];
            $anioFacturado = $request->request->get("form")['anioFacturado'];
            $fechaVencimiento = $request->request->get("form")['fechaVencimiento'];

            $proceso = [1=>"Activación",2=>"Finalización",3=>"Modificación"];
            $rol = $session->get("rol");
            $idSedeAgencia = $session->get("idSedeAgencia");
            $rt = null;

            $esa = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$idSedeAgencia]);

            $query = "Update facturas set periodo_actual = ? ";
            if($tipoProceso == 1){//Activar
              if($fechaVencimiento != '' and $fechaVencimiento != null){
                $query .= ", fecha_vencimiento = ? where facturas.id_factura not in (select pagos.id_factura from pagos) and mes_facturado = ? and anio_facturado = ? and id_empresa = ?";
                $params = [
                  1,//Para activar un periodo
                  $fechaVencimiento." 23:59:59",
                  $mesFacturado,
                  $anioFacturado,
                  $esa[0]->getIdEmpresa()->getIdEmpresa()
                ];

                $rt = $main->rawQueryDoctrine($query,$params,"update");

                //Auditoria agrega un bloque a la cadena.
                $dataAud = [
                  "accion"=>"Update",
                  "tabla"=>"facturas",
                  "id_datos"=>"Finalizar Recaudo - Activar",
                  "data"=>$params
                ];

                $blockchain->addBlock(new Block($dataAud));
              }else{
                return $this->render('facturas/finalizarActivarRecaudo.html.twig', array(
                    'form' => $form->createView(),
                    "mensajes" => "Ha decido realizar el proceso de '".$proceso[$tipoProceso]."' del periodo de recaudos seleccionado, pero no ha seleccionado una fecha de vencimiento para este periodo. Selecciónela y vuelva ha intentarlo."
                ));
              }
            }else if($tipoProceso == 2){//Finalizar
              $query .= " where facturas.id_factura not in (select pagos.id_factura from pagos) and mes_facturado = ? and anio_facturado = ? and id_empresa = ?";
              $params = [
                0,//Para finalizar un periodo
                $mesFacturado,
                $anioFacturado,
                $esa[0]->getIdEmpresa()->getIdEmpresa()
              ];
              $rt = $main->rawQueryDoctrine($query,$params,"update");

              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Update",
                "tabla"=>"facturas",
                "id_datos"=>"Finalizar Recaudo - Finalizar",
                "data"=>$params
              ];

              $blockchain->addBlock(new Block($dataAud));
            }else if($tipoProceso == 3){//Modificar solo fechas de vencimiento de facturas sin pagos.
              if($fechaVencimiento != '' and $fechaVencimiento != null){
                $queryFecha = "Update facturas set fecha_vencimiento = ? where facturas.id_factura not in (select pagos.id_factura from pagos) and mes_facturado = ? and anio_facturado = ? and id_empresa = ?";
                $params = [
                  $fechaVencimiento." 23:59:59",//Fecha a modificar
                  $mesFacturado,
                  $anioFacturado,
                  $esa[0]->getIdEmpresa()->getIdEmpresa()
                ];

                $rt = $main->rawQueryDoctrine($queryFecha,$params,"update");

                //Auditoria agrega un bloque a la cadena.
                $dataAud = [
                  "accion"=>"Update",
                  "tabla"=>"facturas",
                  "id_datos"=>"Finalizar Recaudo - Modifica fechas de vencimiento",
                  "data"=>$params
                ];

                $blockchain->addBlock(new Block($dataAud));
              }else{
                return $this->render('facturas/finalizarActivarRecaudo.html.twig', array(
                    'form' => $form->createView(),
                    "mensajes" => "Ha decido realizar el proceso de '".$proceso[$tipoProceso]."' del periodo de recaudos seleccionado, pero no ha seleccionado una fecha de vencimiento para este periodo. Selecciónela y vuelva ha intentarlo."
                ));
              }
            }

            //Registra la cadena de bloques en la tabla Auditoria.
            $blockchain->registerChain();

            return $this->render('facturas/finalizarActivarRecaudo.html.twig', array(
                'form' => $form->createView(),
                "mensajes" => "Proceso de '".$proceso[$tipoProceso]."' terminado correctamente."
            ));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Finalizar Facturación",
            "id_datos"=>"vista finalizar facturación",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('facturas/finalizarActivarRecaudo.html.twig', array(
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

    //Calcula los años para los Año Facturado.
    public function getAniosPeriodos(){
       $session = new Session();
       $años = [];
       $minAños = [];
       $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
       $rawem = $em->getConnection();
       $idSA = $session->get("idSedeAgencia");

       $EmpUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
       ->findBy(["idSedeAgencia"=>$idSA]);

       foreach ($EmpUsuario as $key => $value) {
           $queryfacturas = "SELECT MIN(F.anio_facturado) as anioMinimo FROM facturas as F WHERE F.id_empresa = ".$value->getIdEmpresa()->getIdEmpresa()."";

           $stmt = $rawem->prepare($queryfacturas);
           $rowAffected = $stmt->execute();
           $facturas = $stmt->fetchAll();
           array_push($minAños,$facturas[0]["anioMinimo"]);
       }

       asort($minAños);//Ordena Ascendentemente.
       $lenMax = count($minAños)+10;

       for($i=0;$i<=$lenMax;$i++){
         $años[$minAños[0]+$i] = ($minAños[0]+$i);
       }

       return $años;
   }

    /**
     * Creates a new factura entity.
     *
     */
    public function newAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('crear factura');

        if($permiso === true){
          $factura = new Facturas();
          $form = $this->createForm('AppBundle\Form\FacturasType', $factura);
          $form->handleRequest($request);
          //Auditoria.
          $blockchain = new Blockchain($em);

          if ($form->isSubmitted() && $form->isValid()) {
              $abono = $request->request->get('appbundle_facturas')['isAbono'];
              $nroFact = $request->request->get('appbundle_facturas')['nroFactura'];
              $idEmpresa = $request->request->get('appbundle_facturas')['idEmpresa'];
              //Validar el número de la factura dentro del historial de facturación.
              $valFact = $em->getRepository("AppBundle:Facturas")->findBy(["nroFactura"=>$nroFact,"idEmpresa"=>$idEmpresa]);

              if(count($valFact) > 0 && $abono == 0){ //Si la factura existe en el sistema y la nueva factura no es un abono, muestra un error.
                return $this->render('facturas/new.html.twig', array(
                    'factura' => $factura,
                    'form' => $form->createView(),
                    "mensajes" => "No se puede continuar, porque el número de la factura (".$nroFact.") ya se encuentra registrada en el sistema."
                ));
              }else {
                $mesFact = $request->request->get('appbundle_facturas')['mesFacturado'];
                $fechaVencimiento = $request->request->get('appbundle_facturas')['fechaVencimiento'];
                $nombreUsuario = $request->request->get('appbundle_facturas')['nombreUsuario'];

                $factura->setNombreUsuario(strtoupper($nombreUsuario));
                $factura->setFechaVencimiento(new \DateTime($fechaVencimiento." 23:59:59", new \DateTimeZone('America/Bogota')));
                $factura->setMesFacturado(ltrim($mesFact,"0"));

                $em->persist($factura);
                $em->flush();

                //Auditoria
                $dataAud = [
                  "accion"=>"Insert",
                  "tabla"=>"facturas",
                  "id_datos"=>$factura->getIdFactura(),
                  "data"=>$factura->getArrayData()//Obtengo un array con los datos del objeto Entity.
                ];

                $blockchain->addBlock(new Block($dataAud));
                $blockchain->registerChain();

                return $this->redirectToRoute('facturas_show', array('idFactura' => $factura->getIdfactura()));
              }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Crear Factura / Abono",
            "id_datos"=>"vista crear factura - abono",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('facturas/new.html.twig', array(
              'factura' => $factura,
              'form' => $form->createView(),
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

    /**
     * Finds and displays a factura entity.
     *
     */
    public function showAction(Request $request,Facturas $factura)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('ver factura');

        if($permiso === true){
          $deleteForm = $this->createDeleteForm($factura);
          //Auditoria.
          $blockchain = new Blockchain($em);

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Ver Factura",
            "id_datos"=>"vista ver factura, idFactura = ".$factura->getIdFactura(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('facturas/show.html.twig', array(
              'factura' => $factura,
              'delete_form' => $deleteForm->createView(),
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

    /**
     * Displays a form to edit an existing factura entity.
     *
     */
    public function editAction(Request $request, Facturas $factura)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('editar factura');

        if($permiso === true){
          $deleteForm = $this->createDeleteForm($factura);
          $editForm = $this->createForm('AppBundle\Form\FacturasType', $factura);
          $editForm->handleRequest($request);
          //Auditoria.
          $blockchain = new Blockchain($em);

          if ($editForm->isSubmitted() && $editForm->isValid()) {
              $mesFact = $request->request->get('appbundle_facturas')['mesFacturado'];
              $fechaVencimiento = $request->request->get('appbundle_facturas')['fechaVencimiento'];
              $nombreUsuario = $request->request->get('appbundle_facturas')['nombreUsuario'];

              $factura->setNombreUsuario(strtoupper($nombreUsuario));
              $factura->setFechaVencimiento(new \DateTime($fechaVencimiento." 23:59:59", new \DateTimeZone('America/Bogota')));
              $factura->setMesFacturado(ltrim($mesFact,"0"));

              $em->flush();

              //Auditoria
              $dataAud = [
                "accion"=>"Update",
                "tabla"=>"facturas",
                "id_datos"=>$factura->getIdFactura(),
                "data"=>$factura->getArrayData()//Obtiene el arreglo de los datos del objeto Entity.
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              return $this->render('facturas/edit.html.twig', array(
                  'factura' => $factura,
                  'edit_form' => $editForm->createView(),
                  'delete_form' => $deleteForm->createView(),
                  'mensajes' => "Se realizaron los cambios correctamente."
              ));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Editar Factura",
            "id_datos"=>"vista editar factura, idFactura = ".$factura->getIdFactura(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('facturas/edit.html.twig', array(
              'factura' => $factura,
              'edit_form' => $editForm->createView(),
              'delete_form' => $deleteForm->createView(),
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

    /**
     * Deletes a factura entity.
     *
     */
    public function deleteAction(Request $request, Facturas $factura)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('eliminar factura');

        if($permiso === true){
          $form = $this->createDeleteForm($factura);
          $form->handleRequest($request);
          //Auditoria.
          $blockchain = new Blockchain($em);

          if ($form->isSubmitted() && $form->isValid()) {
              $em->remove($factura);

              //Auditoria.
              $dataAud = [
                "tabla"=>"facturas",
                "accion"=>"Delete",
                "id_datos"=>$factura->getIdFactura(),
                "data"=>$factura->getArrayData()//Se obtiene los datos del objeto Entity a eliminar.
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              $em->flush();
          }

          return $this->redirectToRoute('facturas_index');
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

    /**
     * Creates a form to delete a factura entity.
     *
     * @param Facturas $factura The factura entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Facturas $factura)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('facturas_delete', array('idFactura' => $factura->getIdfactura())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * subir Facturación masivamente .csv.
     *
     */
    public function subirFacturacionAction(Request $request)
    {
      ini_set("max_execution_time", "1800");//20 Min.
      ini_set('memory_limit', '1024M');

      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('subir facturacion');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          //Empresas Sedes Agencias.
          $ESA = $em->getRepository("AppBundle:EmpresasSedesAgencias")
          ->findBy(array("idSedeAgencia"=>$session->get('idSedeAgencia')));

          //Se crea un formulario, con el cual se sube el archivo csv.
          $form = $this->createFormBuilder()
          ->add("selectFacturacion",ChoiceType::class,array(
            "label"=>"Select Facturación",
            "choices"=>array("Subir Facturación"=>1,"Subir Abonos"=>3,"Subir Ajustes"=>2),
            "data"=>1,//Se selecciona la primera opción por defecto.
            "expanded"=>true,//al estar en true se crea options o checks buttons en vez del select.
            "multiple"=>false,
            "attr"=>array("class"=>"m-1 radio")
          ))
          ->add("archivo",FileType::class,array(
            "label"=>"Seleccione Archivo:",
            "attr"=>array("class"=>"form-control btn btn-success btn-sm w-100","placeholder"=>"Elija archivo .csv")
          ))->getForm();

          $form->handleRequest($request);

          if ($form->isSubmitted() && $form->isValid()) {
            $selectFacturacion = $request->request->get('form')['selectFacturacion'];

            $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);
            $logs = [];

            $file = $request->files->get('form')['archivo'];
            $extension = $file->getClientOriginalExtension();
            $filedatacsv = $form['archivo']->getData();
            $fileSize = $file->getClientSize();
            $fileRows = null; //Almacena los registros que se subieron o ajustaron en la facturación.

            if($fileSize <= 5000000){ // Menor o igual a 5 Mb.
              if($extension == 'csv'){
                // decoding CSV contents
                $data = $serializer->decode(file_get_contents($filedatacsv), 'csv');

                if($selectFacturacion == 2){//Ajustes
                  // $dataReturn = $this->DeleteWithPayments($data);
                  $mensajes = "Se subió y procesó los Ajustes corréctamente.";
                  $this->SerializerAndExecutedRawQuery($data,"facturas","update");//Ejecutar la actualización con raw_query;
                  $this->UpdateBalancePayments($data);//Actualiza los saldos de los pagos ya realizados.
                  $fileRows = count($data);
                }else if($selectFacturacion == 1){//Ingreso nueva facturación
                  $mensajes = "Se subió y procesó la facturación corréctamente.";
                  $this->setPeriodoActualInvoices();//Setea las facturas con periodoActual igual a 1 y lo cambia a 0.
                  $this->SerializerAndExecutedRawQuery($data,"facturas","insert");//Ejecutar la inserción con raw_query;
                  $fileRows = count($data);
                }else if($selectFacturacion == 3){//Ingreso de Abonos
                  $mensajes = "Se subió y procesó los Abonos a la facturación corréctamente.";
                  $this->SerializerAndExecutedRawQuery($data,"facturas","insert");//Ejecutar la inserción con raw_query;
                  $fileRows = count($data);
                }

                return $this->redirectToRoute("subirFacturacion",[
                  "mensajes"=> $mensajes
                ]);
              }else{
                return $this->render('facturas/subirFacturacion.html.twig', array(
                    'mensajes' => "El tipo de archivo cargado no está permitido. Archivo Encontrado .".$extension." tipo de archivo requerido .csv.",
                    'form' => $form->createView(),
                    "empresas" => $ESA
                ));
              }
            }else{
              return $this->render('facturas/subirFacturacion.html.twig', array(
                  'mensajes' => "El archivo cargado supera el tamaño permitido (5 Mb).",
                  'form' => $form->createView(),
                  "empresas" => $ESA
              ));
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Subir Facturación",
            "id_datos"=>"vista subir facturación",
            "data"=>["cargo vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          $mensajesFlash = $session->getFlashBag()->get('Error');

          return $this->render('facturas/subirFacturacion.html.twig', array(
              'form' => $form->createView(),
              "empresas" => $ESA,
              'Error' => count($mensajesFlash) > 0 ? $mensajesFlash[0] : null
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

    //Resetea la anterior facturación en el campo periodo_actual a 0.
    private function setPeriodoActualInvoices(){
      $em = $this->getDoctrine()->getManager();
      $session = new Session();
      $rol = $session->get("rol");
      //Auditoria
      $blockchain = new Blockchain($em);

      $esa = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(
        ["idSedeAgencia" => $session->get('idSedeAgencia')]
      );

      //Busca todas las facturas que tengan como periodo_actual igual a 1.
      //y el idEmpresa del usuario actual, en este caso solo será Administrador.
      $sql = "SELECT id_factura FROM facturas
      WHERE periodo_actual = 1 AND id_empresa = ".$esa[0]->getIdEmpresa()->getIdEmpresa();

      $stmt = $em->getConnection()->prepare($sql);
      $stmt->execute();
      $facturas = $stmt->fetchAll();

      if(count($facturas) > 0){
        //foreach ($facturas as $key => $factura) {
          //$sqlUpdate = "UPDATE facturas SET periodo_actual = 0 WHERE id_factura = ".$factura['id_factura'];
          $sqlUpdate = "UPDATE facturas SET periodo_actual = 0 WHERE id_empresa = ".$esa[0]->getIdEmpresa()->getIdEmpresa();
          $stmtUpdate = $em->getConnection()->prepare($sqlUpdate);
          $stmtUpdate->execute();
        //}

        //Auditoria
        $dataAud = [
          "tabla"=>"facturas",
          "accion"=>"Update",
          "id_datos"=>"Finalizar el periodo anterior antes de subir la nueva facturación del nuevo periodo.",
          "data"=>["periodo_actual = 0"]
        ];

        $blockchain->addBlock(new Block($dataAud));

        //Registra la cadena en la tabla Auditoria.
        $blockchain->registerChain();
      }
    }

    //Función que elimina del data obtenido del csv, aquellas facturas con un pago registrado. NO UTILIZADA.
    private function DeleteWithPayments($data){
      $em = $this->getDoctrine()->getManager();
      $dataResult = $data;

      foreach ($data as $key => $value) {
        $pagos = $em->getRepository("AppBundle:Pagos")
        ->createQueryBuilder("P")->join("AppBundle:Facturas","F","WITH","F.idFactura=P.idFactura")
        ->where("F.nroFactura = :nroFact")->setParameter("nroFact",$value['nro_factura'])
        ->andWhere("F.periodoActual = 0")
        ->andWhere("F.idEmpresa = :idEmpresa")
        ->setParameter("idEmpresa",$value["id_empresa"])
        ->getQuery()->getResult();

        if (count($pagos) > 0){
          unset($dataResult[$key]);//Elimina toda la key del arreglo. En esta caso todo el registro de la factura que ya tiene un pago.
        }
      }

      return $dataResult;
    }

    //Función que actualiza los saldos de los pagos registrados de cada ajuste realizao en a la factura.
    private function UpdateBalancePayments($data){
      $em = $this->getDoctrine()->getManager();
      $status = false;

      foreach ($data as $key => $value) {
        $em->getConnection()->beginTransaction();
        try{
          $pagos = $em->getRepository("AppBundle:Pagos")
          ->createQueryBuilder("P")->join("AppBundle:Facturas","F","WITH","F.idFactura=P.idFactura")
          ->where("F.nroFactura = :nroFact")->setParameter("nroFact",$value['nro_factura'])
          ->andWhere("F.idEmpresa = :idEmpresa")
          ->setParameter("idEmpresa",$value["id_empresa"])
          ->getQuery()->getResult();

          if(count($pagos) > 0){
            //Auditoria
            $blockchain = new Blockchain($em);

            $diff = $value["valor_factura"] - $pagos[0]->getVlrPago();

            //$vale['valor_factura'] es el valor del ajuste aplicar.
            if($value['valor_factura'] > $pagos[0]->getVlrPago()){//El usuario debe un saldo.
              $tipoPago = $em->getRepository("AppBundle:TipoPagos")->findBy(["idTipoPago"=>2]);//Parcial
              //Actualiza saldos de cada pago.
              $pagos[0]->setSaldo($diff);
            }else if($value['valor_factura'] < $pagos[0]->getVlrPago()){//El usuario tiene un saldo a favor.
              $tipoPago = $em->getRepository("AppBundle:TipoPagos")->findBy(["idTipoPago"=>3]);//Avance
              //Actualiza saldos de cada pago.
              $pagos[0]->setSaldo($diff);
            }else if($value['valor_factura'] == $pagos[0]->getVlrPago()){
              $tipoPago = $em->getRepository("AppBundle:TipoPagos")->findBy(["idTipoPago"=>1]);//Total
              //Actualiza saldos de cada pago.
              $pagos[0]->setSaldo($diff);
            }

            $pagos[0]->setIdTipoPago($tipoPago[0]);
            $em->flush($pagos[0]);

            //Auditoria.
            $dataAud = [
              "tabla"=>"pagos",
              "accion"=>"Update",
              "id_datos"=>$pagos[0]->getIdPago(),
              "data"=>$pagos[0]->getArrayData()
            ];

            $blockchain->addBlock(new Block($dataAud));
            //Registra la cadena en la tabla Auditoria.
            $blockchain->registerChain();

            $status = true;
          }
          $em->getConnection()->commit();

        }catch(\Exception $er){
          $blockchain->chain = [];//Se elimina los bloques que se hayan agregado a la cadena en caso de error.
          $this->get('session')->getFlashBag()->add(
              'Error',
              'Error al subir facturación. '.$er->getMessage()
          );
          $em->getConnection()->rollBack();
          return $this->redirectToRoute("subirFacturacion");
        }
      }

      return $status;
    }

    private function SerializerAndExecutedRawQuery($data,$tabla,$action = "insert"){
      $em = $this->getDoctrine()->getManager();
      //Auditoria
      $blockchain = new Blockchain($em);

      $em->getConnection()->beginTransaction();
      try{
        foreach ($data as $value) {
          $fields = [];
          $values = [];
          $fieldsImplode = '';
          $valuesImplode = '';

          // if ($value['valor_factura'] != 0){//Solo se ingresan los que sean diferentes a cero.
          if ($action == 'insert'){
            $sql = " INSERT INTO ".$tabla." ";
          }else if($action == 'update'){
            $sql = " UPDATE ".$tabla." SET ";
          }

          foreach ($value as $key => $val) {
            $strSlash = strrpos($val,"/");
            $strTwoPoints = strrpos($val,":");

            if($action == 'insert'){
              array_push($fields,$key);

              if($strSlash == false && $strTwoPoints == false){
                if($val === ''){
                  array_push($values,"null");
                }else{
                  array_push($values,"'".$val."'");
                }
              }else{//Es un campo fecha;
                $currentDate = str_replace("/","-",$val);
                array_push($values,"'".$currentDate."'");
              }
            }else if($action == 'update'){
              if($key == "nro_factura" || $key == "id_empresa"){//|| $key == "periodo_actual"
                array_push($values,$key." = '".$val."'");
                array_push($fields,$key." = '".$val."'");
              }else if($key == "fecha_vencimiento"){
                $currentDate = str_replace("/","-",$val);
                array_push($fields,$key." = '".$currentDate."'");
              }else{
                array_push($fields,$key." = '".$val."'");
              }
            }
          }

          $fieldsImplode = implode(",",$fields);

          if($action == 'insert'){
            $valuesImplode = implode(",",$values);
            $sql .= "(".$fieldsImplode.") values(".$valuesImplode.")";

            $description = "Sube facturación - periodo actual";
            $dataAuditoria = ["Cargó facturación"];
          }else if($action == 'update'){
            $valuesImplode = implode(" AND ",$values);
            $sql .= $fieldsImplode." WHERE id_factura = (Select id_factura From (SELECT id_factura FROM facturas WHERE ".$valuesImplode." AND is_abono = 0 GROUP BY id_factura ORDER BY id_factura ASC) AS fact1)";

            $description = "Sube Ajustes a la facturación - periodo actual";
            $dataAuditoria = ["Cargó Ajustes a la facturación"];
          }

          $stmt = $em->getConnection()->prepare($sql);
          $stmt->execute();
          //}
        }

        $em->getConnection()->commit();

      }catch(\Exception $er){
        $blockchain->chain = [];//Se elimina los bloques que se hayan agregado a la cadena en caso de error.
        $this->get('session')->getFlashBag()->add(
            'Error',
            'Error al subir facturación. '.$er->getMessage()
        );
        $em->getConnection()->rollBack();
        return $this->redirectToRoute("subirFacturacion");
      }

      //Auditoria.
      $dataAud = [
        "tabla"=>$tabla,
        "accion"=>ucwords($action),
        "id_datos"=>$description,
        "data"=>$dataAuditoria
      ];

      $blockchain->addBlock(new Block($dataAud));
      //Registra la cadena en la tabla Auditoria.
      $blockchain->registerChain();

    }

    // Función para organizar y ejecutar las consultas para guardar o actualizar registros cualquier tabla,
    // a partir de los datos de un archivo .csv.
    private function SerializedAndExecuted(Request $request = null,$data,$tabla){
      $em = $this->getDoctrine()->getManager();

      if($request != null){
        //$selectFacturacion = $request->request->get('form')['selectFacturacion'];

        //if($selectFacturacion != '' && $selectFacturacion != null){
          $em->getConnection()->beginTransaction(); // suspend auto-commit
          try {
            $keysOriginal = array_keys($data[0]);
            $keys = preg_replace('/_/i'," ",$keysOriginal);
            $pos = [];//Posicion de los Id de las relaciones.

            //Transformo las keys de los nombre de los campos del csv a los nombres de los metodos de la clase
            //de la tabla que se desea insertar los datos.
            foreach ($keys as $k => $val) {
              $keys[$k] = "set".preg_replace('/ /i',"",ucwords(strtolower($val)," "));
              $posRelationship = strrpos($keys[$k],"Id");
              if($posRelationship != false){
                array_push($pos,$k);
              }
            }

            foreach ($data as $key => $value) {
              $entity = new $tabla;
              foreach ($keys as $ky => $vlr) {
                if(!in_array($ky,$pos)){
                  $posDosPuntos = strrpos($value[$keysOriginal[$ky]],":");
                  $posDosPuntos;

                  if($posDosPuntos !== false){
                    $currentDate = str_replace("/","-",$value[$keysOriginal[$ky]]);
                    $entity->$vlr(new \DateTime($currentDate));
                  }else{
                    $entity->$vlr($value[$keysOriginal[$ky]]);
                  }
                }else{
                    $entityC = preg_replace('/set/i',"",$vlr);//Se Obtiene el nombre del campo.
                    $tableEntity = preg_replace('/id/i',"",$entityC)."s";

                    $entityC = lcfirst($entityC);
                    $idEntity = $em->getRepository("AppBundle:Empresas")
                    ->findBy(array($entityC=>$value[$keysOriginal[$ky]]));

                    $entity->$vlr($idEntity[0]);
                }
              }

              $em->persist($entity);
              $em->flush();
            }

            $em->getConnection()->commit();
          } catch (Exception $e) {
              $em->getConnection()->rollBack();
              throw $e;
          }
        //}
      }
    }
}
