<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Novedades;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;

//Auditoria.
use AppBundle\Blockchain\Blockchain;
use AppBundle\Blockchain\Block;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * Novedade controller.
 *
 */
class NovedadesController extends Controller
{
    /**
     * Lists all novedade entities.
     *
     */
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('novedades');

        if($permiso === true){
          $blockchain = new Blockchain($em);
          //Paginador
          $paginator = $this->get('knp_paginator');

          $form = $this->createFormBuilder()
          ->add("fechaNovedad",DateType::class,array(
            "label"=>"Fecha Novedad:",
            "widget"=>"single_text",
            "required"=>false,
            "attr"=>["class"=>"form-control w-100"]
          ))->add("modulo",ChoiceType::class,array(
            "label"=>"Módulo:",
            "required"=>false,
            "choices"=>[
              "Pagos"=>"pagos",
              "Cierres de Cajas"=>"cierres de cajas"
            ],
            "placeholder"=>"Módulo",
            "attr"=>array("class"=>"form-control w-100 select2")
          ))->add('idUsuario',EntityType::class,array(
            "label"=>"Usuario *",
            "required"=>false,
            "attr"=>array("class"=>"form-control w-50 select2"),
            "class"=>"AppBundle:Usuarios",
            "placeholder"=>"Usuario",
            'choice_attr' => function($choice, $key, $value) {
              $session = new Session();

              if($session->get('rol') == 'Cajero'){
                if($value == $session->get('idUsuario')){
                  return ['selected' => 'selected'];
                }else{
                  return ['value' => $value];
                }
              }else{
                return ['value' => $value];
              }
            },
            "query_builder"=>function(EntityRepository $er){
              $session = new Session();
              $query = null;
              $em = $GLOBALS['kernel']->getContainer()
              ->get('doctrine')->getEntityManager();

              $agencia = $em->createQuery("
                SELECT A FROM
                AppBundle:Agencias A
                WHERE A.nitAgencia = :nitAgencia
              ")->setParameter("nitAgencia",$session->get('nitAgencia'))->getResult();

              //Validación para Administradores de Agencias
              if($session->get('rol') == 'Administrador'){
                $query = $er->createQueryBuilder("U")
                ->innerJoin("AppBundle:EmpresasSedesAgencias","ESA","WITH","U.idSedeAgencia=ESA.idSedeAgencia")
                ->innerJoin("AppBundle:SedesAgencias","SA","WITH","ESA.idSedeAgencia=SA.idSedeAgencia")
                ->innerJoin("AppBundle:Agencias","A","WITH","SA.idAgencia=A.idAgencia")
                ->where("SA.idAgencia = :idAgencia")
                ->setParameter("idAgencia",$agencia[0]->getIdAgencia());
              }else if($session->get('rol') == 'Administrador Agencias'){
                $query = $er->createQueryBuilder("U")
                ->leftJoin("AppBundle:EmpresasSedesAgencias","ESA","WITH","U.idSedeAgencia=ESA.idSedeAgencia")
                ->leftJoin("AppBundle:SedesAgencias","SA","WITH","ESA.idSedeAgencia=SA.idSedeAgencia")
                ->leftJoin("AppBundle:Agencias","A","WITH","SA.idAgencia=A.idAgencia")
                ->where("SA.idAgencia = :idAgencia")
                ->setParameter("idAgencia",$agencia[0]->getIdAgencia());
              }else if($session->get('rol') == 'Cajero'){//Validación para Cajeros.
                $query = $er->createQueryBuilder("U")
                ->where("U.idUsuario = :idUsu")
                ->setParameter("idUsu",$session->get('idUsuario'));
              }

              return $query;
            }
          ))->add('tipoNovedad',EntityType::class,[
            "label"=>"Tipo Novedad",
            "required"=>false,
            "placeholder"=>"Tipo Novedad",
            "attr"=>array("class"=>"form-control w-100 select2"),
            "class"=>"AppBundle:TipoNovedades"
          ])->getForm();

          $form->handleRequest($request);

          $ESA = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$session->get('idSedeAgencia')]);

          $novedades = $em->getRepository('AppBundle:Novedades')->createQueryBuilder("N")
          ->OrderBy("N.fechaHoraNovedad","DESC");

          if($session->get("rol") == "Administrador"){
            $novedades->andWhere("N.idEmpresa = :idEmp")
            ->setParameter("idEmp",$ESA[0]->getIdEmpresa()->getIdEmpresa());
          }

          if ($form->isSubmitted() && $form->isValid()) {
            $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);

            //Filtros de busqueda en el listado.
            $fecha = $request->request->get('form')['fechaNovedad'];
            $modulo = $request->request->get('form')['modulo'];
            $idUsuario = $request->request->get('form')['idUsuario'];
            $tipoNovedad = $request->request->get('form')['tipoNovedad'];
            $exportType = $request->request->get('exportType');

            if($fecha != '' && $fecha != null){
              $novedades->andWhere("DATE(N.fechaHoraNovedad) = :fecha")
              ->setParameter("fecha",$fecha);
            }

            if($modulo != '' && $modulo != null){
              $novedades->andWhere("N.moduloAfectado = :modulo")
              ->setParameter("modulo",$modulo);
            }

            if($idUsuario != '' && $idUsuario != null){
              $novedades->andWhere("N.idUsuario = :id")
              ->setParameter("id",$idUsuario);
            }

            if($tipoNovedad != '' && $tipoNovedad != null){
              $novedades->andWhere("N.idTipoNovedad = :idTipoNovedad")
              ->setParameter("idTipoNovedad",$tipoNovedad);
            }

            $noved = $paginator->paginate(
                $novedades,
                $request->query->getInt('page', 1),
                10
            );

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Novedades",
              "id_datos"=>"vista novedades - Lista Filtrada",
              "data"=>["cargó vista filtrada",
                "fecha"=>$fecha,
                "modulo"=>$modulo,
                "idUsuario"=>$idUsuario,
                "tipoNovedad"=>$tipoNovedad
              ]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            if($exportType == 'csv'){
              $output = [];
              $datos = $novedades->getQuery()->getResult();

              foreach ($datos as $key => $value) {
                  array_push($output,[
                    "TxHash"=>$value->getTxHash(),
                    "Fecha / Hora Novedad"=>$value->getFechaHoraNovedad()->format("Y-m-d H:i:s"),
                    "Modulo Afectado"=>$value->getModuloAfectado(),
                    "ID Datos Afectados"=>$value->getIdentificadorData(),
                    "Datos Antes de ser Afectados"=>str_replace("~"," | ",$value->getAnteriorData()),
                    "Observaciones Novedad"=>$value->getObservacionesNovedad(),
                    "Tipo Novedad"=>$value->getIdTipoNovedad(),
                    "Usuario de la Novedad"=>$value->getIdUsuario(),
                    "Empresa"=>$value->getIdEmpresa()
                  ]);
              }
              $outputCSV = $serializer->encode($output, 'csv');

              return new Response(
                  $outputCSV,
                  200,
                  array(
                      'Content-Type'          => 'text/csv; charset=utf-8',
                      'Content-Disposition'   => 'attachment; filename="Novedades.csv"'
                  )
              );
            }else{
              return $this->render('novedades/index.html.twig', array(
                  'novedades' => $noved,
                  "form" => $form->createView()
              ));
            }

          }

          $novedades = $paginator->paginate(
              $novedades,
              $request->query->getInt('page', 1),
              10
          );

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entró al módulo de Novedades",
            "id_datos"=>"vista novedades - Listar",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('novedades/index.html.twig', array(
              'novedades' => $novedades,
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

    /**
     * Finds and displays a novedade entity.
     *
     */
    public function showAction(Request $request, Novedades $novedad)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('ver novedad');

        if($permiso === true){
          $blockchain = new Blockchain($em);

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entró al módulo de Ver Novedad",
            "id_datos"=>"vista ver novedad, idNovedad = ".$novedad->getIdNovedad(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('novedades/show.html.twig', array(
              'novedades' => $novedad,
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
     * Creates a new agencia entity.
     *
     */
    public function newAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('crear novedad');

        if($permiso === true){
          $novedad = new Novedades();
          //Auditoria.
          $blockchain = new Blockchain($em);
          $form = $this->createForm('AppBundle\Form\NovedadesType');
          $form->handleRequest($request);

          if ($form->isSubmitted() && $form->isValid()) {
            $empresa = $request->request->get("appbundle_agencias")["agenciasEmpresas"];
            $nitAgencia = $request->request->get("appbundle_agencias")["nitAgencia"];
            $habilitarAgencia = $request->request->get("habilitarAgencia");

            $ESA = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy([
                "idSedeAgencia"=>$session->get("idSedeAgencia")
            ]);

            //Auditoria
            $dataAud2 = [
              "accion"=>"Insert",
              "tabla"=>"novedades",
              "id_datos"=>$novedad->getIdNovedad(),
              "data"=>$novedad->getArrayData()//Obtengo un array con los datos del objeto Entity.
            ];

            $blockchain->addBlock(new Block($dataAud2));
            $blockchain->registerChain();

            return $this->render('agencias/new.html.twig', array(
                'novedades' => $novedad,
                'form' => $form->createView()
            ));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Crear Novedad",
            "id_datos"=>"vista crear novedad",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('novedades/new.html.twig', array(
              'novedades' => $novedad,
              'form' => $form->createView(),
              "novedades" => true//Para cargar modulos y librerías de angularjs
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
