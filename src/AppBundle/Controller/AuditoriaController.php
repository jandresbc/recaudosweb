<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Auditoria;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Session\Session;

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
 * Auditorium controller.
 *
 */
class AuditoriaController extends Controller
{
    /**
     * Lists all auditorium entities.
     *
     */
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
          $em = $this->getDoctrine()->getManager();

          $permiso = $em->getRepository('AppBundle:Permisos')
          ->validarPermiso('auditorias');

          if($permiso === true){
            $blockchain = new Blockchain($em);
            //Paginador
            $paginator = $this->get('knp_paginator');

            $form = $this->createFormBuilder()
            ->add("accion",ChoiceType::class,[
              "label"=>"Acción",
              "choices"=>[
                "Load"=>"Load",
                "Insert"=>"Insert",
                "Select"=>"Select",
                "Update"=>"Update",
                "Delete"=>"Delete",
                "Autentication"=>"Autentication",
                "Logout"=>"Logout"
              ],
              "placeholder"=>"Filtro por Acción",
              "required"=>false,
              "attr"=>["class"=>"form-control select2"]
            ])->add("usuario",EntityType::class,[
              "label"=>"Usuario",
              "placeholder"=>"Filtro por Usuario",
              "required"=>false,
              "attr"=>["class"=>"form-control select2"],
              "class"=>"AppBundle:Usuarios",
              "query_builder"=>function(EntityRepository $er){
                  $session = new Session();
                  $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
                  $rol = $session->get("rol");
                  $idsede = $session->get("idSedeAgencia");

                  $ESA = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$idsede]);

                  $query = $er->createQueryBuilder("U")
                  ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","U.idSedeAgencia=ESA.idSedeAgencia");

                  if($rol == 'Administrador'){
                    $query->andWhere("U.idGrupoUsuario <> 1");
                    foreach ($ESA as $key => $value) {
                      $query->orWhere("ESA.idEmpresa = :idEmp".$key)
                      ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
                    }
                  }

                  return $query;
              }
            ])->getForm();

            $form->handleRequest($request);

            $auditorias = $em->getRepository('AppBundle:Auditoria')->createQueryBuilder("A")
            ->setMaxResults("10")
            ->orderBy("A.idAuditoria","DESC");

            if ($form->isSubmitted() && $form->isValid()) {
              $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);
              //Filtros de busqueda en el listado.
              $accion = $request->request->get('form')['accion'];
              $usuario = $request->request->get('form')['usuario'];
              $exportType = $request->request->get('exportType');

              if($accion != '' && $accion != null){
                $auditorias->andWhere("A.accion like '%".$accion."%'");
              }

              if($usuario != '' && $usuario != null){
                $auditorias->andWhere("A.idUsuario = :idUs")
                ->setParameter("idUs",$usuario);
              }

              $rows = count($auditorias->getQuery()->getResult());

              $audi = $paginator->paginate(
                  $auditorias,
                  $request->query->getInt('page', 1),
                  10
              );

              //Auditoria agrega un bloque a la cadena.
              $dataAud2 = [
                "accion"=>"Load",
                "tabla"=>"Entro al módulo de Auditorias",
                "id_datos"=>"vista auditorias - Lista Filtrada",
                "data"=>["cargó vista filtrada",$accion,$usuario]
              ];

              $blockchain->addBlock(new Block($dataAud2));
              $blockchain->registerChain();

              if($session->has("validacion")){
                $mensajeValidacion = $session->get("validacion");
                $session->remove("validacion");
              }else {
                $mensajeValidacion = null;
              }

              if($exportType == 'csv'){
                $output = [];
                $datos = $auditorias->getQuery()->getResult();

                foreach ($datos as $key => $value) {
                    array_push($output,[
                      //"TxHash"=>$value->getTxHash(),
                      //"BxFrom"=>$value->getBxFrom(),
                      //"BxTo"=>$value->getBxTo(),
                      "Fecha Hora"=>$value->getFechaHora()->format("d/m/Y h:i:s a"),
                      "Accion"=>$value->getAccion(),
                      "Tabla"=>$value->getTabla(),
                      "ID Datos"=>$value->getIdDatos(),
                      "Datos"=>$value->getDatos(),
                      "Usuario"=>$value->getIdUsuario(),
                      "Empresa"=>$value->getIdEmpresa()
                    ]);
                }
                $outputCSV = $serializer->encode($output, 'csv');

                return new Response(
                    $outputCSV,
                    200,
                    array(
                        'Content-Type'          => 'text/csv; charset=utf-8',
                        'Content-Disposition'   => 'attachment; filename="Auditoria.csv"'
                    )
                );
              }else{
                return $this->render('auditoria/index.html.twig', array(
                    'auditorias' => $audi,
                    'form' => $form->createView(),
                    "rows" => $rows,
                    "mensajesValidacion" => $mensajeValidacion
                ));
              }
            }//Fin formulario

            $rows = count($auditorias->getQuery()->getResult());

            $auditorias = $paginator->paginate(
                $auditorias,
                $request->query->getInt('page', 1),
                10
            );

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Auditorias",
              "id_datos"=>"vista auditorias - Listar",
              "data"=>["cargó vista"]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            if($session->has("validacion")){
              $mensajeValidacion = $session->get("validacion");
              $session->remove("validacion");
            }else {
              $mensajeValidacion = null;
            }

            return $this->render('auditoria/index.html.twig', array(
                'auditorias' => $auditorias,
                'form' => $form->createView(),
                "rows" => $rows,
                "mensajesValidacion" => $mensajeValidacion
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
     * Finds and displays a auditorium entity.
     *
     */
    public function showAction(Request $request, Auditoria $auditorium)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
          $em = $this->getDoctrine()->getManager();

          $permiso = $em->getRepository('AppBundle:Permisos')
          ->validarPermiso('ver auditoria');

          if($permiso === true){
            $blockchain = new Blockchain($em);

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Ver Auditoria",
              "id_datos"=>"vista ver auditoria, idAuditoria = ".$auditorium->getIdAuditoria(),
              "data"=>["cargó vista"]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            return $this->render('auditoria/show.html.twig', array(
                'auditorium' => $auditorium,
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

    public function validTxHashAction(Request $request, $idAuditoria,  Auditoria $auditorium)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $blockchain = new Blockchain($em);

        //Auditoria agrega un bloque a la cadena.
        $dataAud = [
          "accion"=>"Load",
          "tabla"=>"Entro al módulo de Validar TxHash Auditoria",
          "id_datos"=>"procedimiento validar TxHash Auditoria, idAuditoria = ".$auditorium->getIdAuditoria(),
          "data"=>["cargó procedimiento"]
        ];

        $blockchain->addBlock(new Block($dataAud));
        $blockchain->registerChain();

        $validacion = $blockchain->validateTxHash($idAuditoria,$auditorium->getIdUsuario()->getIdUsuario());

        return $this->render('auditoria/show.html.twig', array(
            'auditorium' => $auditorium,
            'validacion' => $validacion
        ));
      }else{
        return $this->redirectToRoute("error",
          array('codigo'=>'100')
        );
      }
    }

    public function validateChainAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $blockchain = new Blockchain($em);

        //Auditoria agrega un bloque a la cadena.
        $dataAud = [
          "accion"=>"Load",
          "tabla"=>"Entro al módulo de Validar Cadena de Registros Auditoria",
          "id_datos"=>"procedimiento validar cadena de registros auditoria",
          "data"=>["cargó procedimiento"]
        ];

        $blockchain->addBlock(new Block($dataAud));
        $blockchain->registerChain();

        //Filtros de rango.
        $min = $request->request->get('RangeMin');
        $max = $request->request->get('RangeMax');

        $blockchain->getChain("",$min,$max);
        $validacion = $blockchain->isChainValid();

        $session->set("validacion",json_decode($validacion->getContent()));

        return $this->redirectToRoute('auditoria_index');
      }else{
        return $this->redirectToRoute("error",
          array('codigo'=>'100')
        );
      }
    }
}
