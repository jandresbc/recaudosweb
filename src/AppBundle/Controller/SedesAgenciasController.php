<?php

namespace AppBundle\Controller;

use AppBundle\Entity\SedesAgencias;
use AppBundle\Entity\EmpresasSedesAgencias;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;

//Auditoria.
use AppBundle\Blockchain\Blockchain;
use AppBundle\Blockchain\Block;

/**
 * Sedesagencia controller.
 *
 */
class SedesAgenciasController extends Controller
{
    /**
     * Lists all sedesAgencia entities.
     *
     */
    public function indexAction(Request $request)
    {
        $session = $request->getSession();
        if($session->get('auth') == 1){
            $em = $this->getDoctrine()->getManager();

            $permiso = $em->getRepository('AppBundle:Permisos')
            ->validarPermiso('sedes agencias');

            if($permiso === true){
              $blockchain = new Blockchain($em);
              //Paginador
              $paginator = $this->get('knp_paginator');

              $form = $this->createFormBuilder()
              ->add("nombreSede",TextType::class,array(
                "label"=>"Nombre Sede:",
                "required"=>false,
                "attr"=>array("class"=>"form-control w-100","placeholder"=>"Nombre de la Sede")
              ))->add("agencia",EntityType::class,array(
                "label"=>"Agencia:",
                "required"=>false,
                "placeholder"=>"Seleccione",
                "attr"=>array("class"=>"form-control select2 w-100"),
                "class"=>"AppBundle:Agencias",
                "query_builder"=>function(EntityRepository $er){
                    $session = new Session();
                    $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
                    $rol = $session->get("rol");
                    $idSA = $session->get("idSedeAgencia");

                    $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
                    ->findBy(["idSedeAgencia"=>$idSA]);

                    $query = $er->createQueryBuilder("A")
                    ->join("AppBundle:SedesAgencias","SA","WITH","A.idAgencia = SA.idAgencia")
                    ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","ESA.idSedeAgencia = SA.idSedeAgencia");

                    if($rol == 'Administrador Agencias'){
                      $query->andWhere("A.idAgencia = :idAgencia")
                      ->setParameter("idAgencia",$ESAUsuario[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());
                    }else if($rol == 'Administrador' || $rol == 'Auditor'){
                      $query->andWhere("ESA.idEmpresa = :idEmpresa")
                      ->setParameter("idEmpresa",$ESAUsuario[0]->getIdEmpresa());
                    }else if($rol == 'Cajero'){
                      $query->andWhere("ESA.idSedeAgencia = :idSA")
                      ->setParameter("idSA",$idSA);
                    }

                    return $query;
                }
              ))->add("municipio",EntityType::class,array(
                "label"=>"Municipio:",
                "required"=>false,
                "placeholder"=>"Seleccione",
                "attr"=>array("class"=>"form-control select2 w-100"),
                "class"=>"AppBundle:Divipola",
                "query_builder"=>function(EntityRepository $er){
                    $session = new Session();
                    $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
                    $rol = $session->get("rol");
                    $idSA = $session->get("idSedeAgencia");

                    $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")
                    ->findBy(["idSedeAgencia"=>$idSA]);

                    $query = $er->createQueryBuilder("D")
                    ->join("AppBundle:SedesAgencias","SA","WITH","D.divipola = SA.idDivipola")
                    ->join("AppBundle:Agencias","A","WITH","A.idAgencia = SA.idAgencia")
                    ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","ESA.idSedeAgencia = SA.idSedeAgencia");

                    if($rol == 'Administrador Agencias'){
                      $query->andWhere("A.idAgencia = :idAgencia")
                      ->setParameter("idAgencia",$ESAUsuario[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());
                    }else if($rol == 'Administrador' || $rol == 'Auditor'){
                      $query->andWhere("ESA.idEmpresa = :idEmpresa")
                      ->setParameter("idEmpresa",$ESAUsuario[0]->getIdEmpresa());
                    }else if($rol == 'Cajero'){
                      $query->andWhere("ESA.idSedeAgencia = :idSA")
                      ->setParameter("idSA",$idSA);
                    }

                    return $query;
                }
              ))->getForm();

              $form->handleRequest($request);

              $ESA = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$session->get('idSedeAgencia')]);

              $sedesAgencias = $em->getRepository('AppBundle:EmpresasSedesAgencias')->createQueryBuilder("ESA")
              ->join("AppBundle:SedesAgencias","SA","WITH","SA.idSedeAgencia=ESA.idSedeAgencia")
              ->join("AppBundle:Agencias","A","WITH","A.idAgencia=SA.idAgencia")
              ->orderBy("SA.idSedeAgencia","DESC");

              //Solo se filtra por empresa si el rol es igual a Administrador de Agencias.
              if($session->get("rol") == 'Administrador' || $session->get("rol") == 'Auditor'){
                foreach ($ESA as $key => $value) {
                  $sedesAgencias->orWhere("ESA.idEmpresa = :idEmp".$key)
                  ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
                }
              }else if($session->get("rol") == 'Administrador Agencias'){
                foreach ($ESA as $key => $value) {
                  $sedesAgencias->andWhere("A.idAgencia = :idAgencia".$key)
                  ->setParameter("idAgencia".$key,$value->getIdsedeagencia()->getIdAgencia()->getIdAgencia());
                }
              }

              if ($form->isSubmitted() && $form->isValid()) {
                //Filtros de busqueda en el listado.
                $nombreSede = $request->request->get('form')['nombreSede'];
                $agencia = $request->request->get('form')['agencia'];
                $municipio = $request->request->get('form')['municipio'];

                if($nombreSede != '' && $nombreSede != null){
                  $sedesAgencias->andWhere("SA.nombreSede like :nombre")
                  ->setParameter("nombre","%".$nombreSede."%");
                }

                if($agencia != '' && $agencia != null){
                  $sedesAgencias->andWhere("A.idAgencia = :idagencia")
                  ->setParameter("idagencia",$agencia);
                }

                if($municipio != '' && $municipio != null){
                  $sedesAgencias->andWhere("SA.idDivipola = :iddivi")
                  ->setParameter("iddivi",$municipio);
                }

                $sedesAgencias = $paginator->paginate(
                    $sedesAgencias,
                    $request->query->getInt('page', 1),
                    10
                );

                //Auditoria agrega un bloque a la cadena.
                $dataAud = [
                  "accion"=>"Load",
                  "tabla"=>"Entro al módulo de Sedes Agencias",
                  "id_datos"=>"vista sedes agencias - Lista Filtrada",
                  "data"=>["cargó vista filtrada"]
                ];

                $blockchain->addBlock(new Block($dataAud));
                $blockchain->registerChain();

                return $this->render('sedesagencias/index.html.twig', array(
                    'sedesAgencias' => $sedesAgencias,
                    "form" => $form->createView()
                ));
              }

              $sedes = $paginator->paginate(
                  $sedesAgencias,
                  $request->query->getInt('page', 1),
                  10
              );

              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Load",
                "tabla"=>"Entro al módulo de Sedes Agencias",
                "id_datos"=>"vista sedes agencias - Listar",
                "data"=>["cargó vista"]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              $mensajesFlash = $session->getFlashBag()->get('Error');

              return $this->render('sedesagencias/index.html.twig', array(
                  'sedesAgencias' => $sedes,
                  'form' => $form->createView(),
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

    /**
     * Creates a new sedesAgencia entity.
     *
     */
    public function newAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('crear sede');

        if($permiso === true){
          $sedesAgencia = new SedesAgencias();
          //Auditoria.
          $blockchain = new Blockchain($em);
          $form = $this->createForm('AppBundle\Form\SedesAgenciasType', $sedesAgencia);
          $form->handleRequest($request);

          if ($form->isSubmitted() && $form->isValid()) {
              $empresa = $request->request->get("appbundle_sedesagencias")["empresa"];

              // $em->getConnection()->beginTransaction();
              // try{
                $em->persist($sedesAgencia);
                $em->flush();

                //Auditoria
                $dataAud1 = [
                  "accion"=>"Insert",
                  "tabla"=>"sedes_agencias",
                  "id_datos"=>$sedesAgencia->getIdSedeAgencia(),
                  "data"=>$sedesAgencia->getArrayData()//Obtengo un array con los datos del objeto Entity.
                ];

                $blockchain->addBlock(new Block($dataAud1));

                //Guardar entidad - relación AgenciasEmpresas.
                $EmpresasSedesAgencias = new EmpresasSedesAgencias();
                $EmpresasSedesAgencias->setIdSedeAgencia($sedesAgencia);
                $emp = $em->getRepository("AppBundle:Empresas")->findBy(["idEmpresa"=>$empresa]);
                $EmpresasSedesAgencias->setIdEmpresa($emp[0]);
                $em->persist($EmpresasSedesAgencias);
                $em->flush($EmpresasSedesAgencias);

                //Auditoria
                $dataAud2 = [
                  "accion"=>"Insert",
                  "tabla"=>"empresas_sedes_agencias",
                  "id_datos"=>$EmpresasSedesAgencias->getIdEmpresaSedeAgencia(),
                  "data"=>$EmpresasSedesAgencias->getArrayData()//Obtengo un array con los datos del objeto Entity.
                ];

                $blockchain->addBlock(new Block($dataAud2));

                // $em->getConnection()->commit();
                $blockchain->registerChain();

                return $this->redirectToRoute('sedesagencias_show', array('idSedeAgencia' => $sedesAgencia->getIdsedeagencia()));
              // }catch(\Exception $e){
              //   $blockchain->chain = [];//Reinicializa la cadena de auditoria.
              //   $this->get('session')->getFlashBag()->add(
              //       'Error',
              //       'Error al insertar. '.$e->getMessage()
              //   );
              //   $em->getConnection()->rollback();
              // }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Crear Sede",
            "id_datos"=>"vista crear sede",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('sedesagencias/new.html.twig', array(
              'sedesAgencia' => $sedesAgencia,
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
     * Finds and displays a sedesAgencia entity.
     *
     */
    public function showAction(Request $request, SedesAgencias $sedesAgencia)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('ver sede');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);

          $deleteForm = $this->createDeleteForm($sedesAgencia);

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Ver Sede",
            "id_datos"=>"vista ver sede, idSedeAgencia = ".$sedesAgencia->getIdSedeAgencia(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('sedesagencias/show.html.twig', array(
              'sedesAgencia' => $sedesAgencia,
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
     * Displays a form to edit an existing sedesAgencia entity.
     *
     */
    public function editAction(Request $request, SedesAgencias $sedesAgencia)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('editar sede');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $deleteForm = $this->createDeleteForm($sedesAgencia);
          $editForm = $this->createForm('AppBundle\Form\SedesAgenciasType', $sedesAgencia);
          $editForm->handleRequest($request);

          //Buscamos el registro de la agencia actual.
          $ESedesAgencias = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$sedesAgencia->getIdSedeAgencia()]);
          //Seteamos las agenciasEmpresas para la agencia actual.
          $sedesAgencia->setEmpresa($ESedesAgencias[0]);

          //Global para seleccionar que empresa le pertenece al registro.
          //Se la utiliza en el SedesAgenciasType.php
          $GLOBALS["empresaSedeAgencia"] = $ESedesAgencias[0]->getIdEmpresa()->getIdEmpresa();

          if ($editForm->isSubmitted() && $editForm->isValid()) {
              $empresa = $request->request->get("appbundle_sedesagencias")["empresa"];
              $em->flush();//Actualiza la sede.

              //Auditoria
              $dataAud1 = [
                "accion"=>"Update",
                "tabla"=>"sedes_agencias",
                "id_datos"=>$sedesAgencia->getIdSedeAgencia(),
                "data"=>$sedesAgencia->getArrayData()//Obtiene el arreglo de los datos del objeto Entity.
              ];

              $blockchain->addBlock(new Block($dataAud1));

              //Editar entidad - relación EmpresasSedesAgencias.
              $EmpresasSedesAgencias = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy([
                "idSedeAgencia" => $sedesAgencia->getIdSedeAgencia(),
                "idEmpresa" => $empresa
              ]);

              $emp = $em->getRepository("AppBundle:Empresas")->findBy(["idEmpresa"=>$empresa]);
              $EmpresasSedesAgencias[0]->setIdEmpresa($emp[0]);
              $em->flush($EmpresasSedesAgencias);

              //Auditoria
              $dataAud2 = [
                "accion"=>"Update",
                "tabla"=>"empresas_sedes_agencias",
                "id_datos"=>$EmpresasSedesAgencias[0]->getIdEmpresaSedeAgencia(),
                "data"=>$EmpresasSedesAgencias[0]->getArrayData()//Obtengo un array con los datos del objeto Entity.
              ];

              $blockchain->addBlock(new Block($dataAud2));
              //Registra la cadena.
              $blockchain->registerChain();

              return $this->redirectToRoute('sedesagencias_edit', array('idSedeAgencia' => $sedesAgencia->getIdsedeagencia()));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Editar Sede",
            "id_datos"=>"vista editar sede, idSedeAgencia = ".$sedesAgencia->getIdSedeAgencia(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('sedesagencias/edit.html.twig', array(
              'sedesAgencia' => $sedesAgencia,
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
     * Deletes a sedesAgencia entity.
     *
     */
    public function deleteAction(Request $request, SedesAgencias $sedesAgencia)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('eliminar sede');

        if($permiso === true){
          $form = $this->createDeleteForm($sedesAgencia);
          $form->handleRequest($request);
          //Auditoria.
          $blockchain = new Blockchain($em);

          if ($form->isSubmitted() && $form->isValid()) {

              if($session->get("rol") == "Superusuario" || $session->get("rol") == "Administrador"){//Un superusuario y administrador puede eliminar el registro de una sede.
                try{
                  $em->remove($sedesAgencia);

                  //Auditoria.
                  $dataAud = [
                    "tabla"=>"sedes_agencias",
                    "accion"=>"Delete",
                    "id_datos"=>$sedesAgencia->getIdSedeAgencia(),
                    "data"=>$sedesAgencia->getArrayData()//Se obtiene los datos del objeto Entity a eliminar.
                  ];

                  $blockchain->addBlock(new Block($dataAud));

                  //Elimina la Relación.
                  $EmpresasSedesAgencias = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy([
                    "idSedeAgencia"=>$sedesAgencia->getIdSedeAgencia()
                  ]);

                  $em->remove($EmpresasSedesAgencias[0]);

                  //Auditoria.
                  $dataAud2 = [
                    "tabla"=>"empresas_sedes_agencias",
                    "accion"=>"Delete",
                    "id_datos"=>$EmpresasSedesAgencias[0]->getIdEmpresaSedeAgencia(),
                    "data"=>$EmpresasSedesAgencias[0]->getArrayData()//Se obtiene los datos del objeto Entity a eliminar.
                  ];

                  $blockchain->addBlock(new Block($dataAud2));
                  $blockchain->registerChain();

                  $em->flush();
                }catch(\Exception $e){
                  $blockchain->chain = [];//Resetea la cadena.
                  $this->get('session')->getFlashBag()->add(
                      'Error',
                      'Error al eliminar. Puede deberse a que el registro que intenta eliminar tenga relaciones con otros registros en otras tablas. Si ese es el motivo solo puede desactivar este registro. '.$e->getMessage()
                  );
                }
              }else if($session->get("rol") == "Administrador Agencias"){
                try{
                  // $EmpresasSedesAgencias = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy([
                  //   "idSedeAgencia"=>$sedesAgencia->getIdSedeAgencia()
                  // ]);

                  // $em->remove($EmpresasSedesAgencias[0]);

                  //Auditoria.
                  // $dataAud = [
                  //   "tabla"=>"empresas_sedes_agencias",
                  //   "accion"=>"Delete",
                  //   "id_datos"=>$EmpresasSedesAgencias[0]->getIdEmpresaSedeAgencia(),
                  //   "data"=>$EmpresasSedesAgencias[0]->getArrayData()//Se obtiene los datos del objeto Entity a eliminar.
                  // ];
                  //
                  // $blockchain->addBlock(new Block($dataAud));
                  // $blockchain->registerChain();

                  // Auditoria.
                  $dataAud = [
                    "tabla"=>"sedes_agencias",
                    "accion"=>"Delete",
                    "id_datos"=>"Entro al procedimiento de Eliminar Sede",
                    "data"=>["procedimiento eliminar sede"]
                  ];

                  $blockchain->addBlock(new Block($dataAud));

                  $sedesAgencia->setInactiva(1);//Inactiva la sede al darle eliminar.
                  $em->flush();

                  // Auditoria.
                  $dataAud2 = [
                    "tabla"=>"sedes_agencias",
                    "accion"=>"Update",
                    "id_datos"=>$sedesAgencia->getIdSedeAgencia(),
                    "data"=>$sedesAgencia->getArrayData()//Se obtiene los datos del objeto Entity que se editó.
                  ];

                  $blockchain->addBlock(new Block($dataAud2));
                  $blockchain->registerChain();
                }catch(\Exception $e){
                  $blockchain->chain = [];//Resetea la cadena.
                  $this->get('session')->getFlashBag()->add(
                      'Error',
                      'Error al eliminar. Puede deberse a que el registro que intenta eliminar tenga relaciones con otros registros en otras tablas. Si ese es el motivo solo puede desactivar este registro. '.$e->getMessage()
                  );
                }
              }
          }

          return $this->redirectToRoute('sedesagencias_index');
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

    public function desactivarAction(Request $request, SedesAgencias $sedesAgencia)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('desactivar sede');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $desactivar = $request->query->get("desactivar");

          if($desactivar == true){
            $idSedeAgencia = $request->query->get("idSedeAgencia");
            //$sede = $em->getRepository("AppBundle:SedesAgencias")->findBy(["idSedeAgencia"=>$idSedeAgencia]);
            $sedesAgencia->setInactiva(1);//Se setea a 1 si la sede esta inactiva. 0 para activa.
            $em->flush();

            //Auditoria.
            $dataAud = [
              "tabla"=>"sedes_agencias",
              "accion"=>"Desactivar",
              "id_datos"=>$sedesAgencia->getIdSedeAgencia(),
              "data"=>$sedesAgencia->getArrayData()//Se obtiene los datos del objeto Entity a eliminar.
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            return $this->redirectToRoute('sedesagencias_index');
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

    /**
     * Creates a form to delete a sedesAgencia entity.
     *
     * @param SedesAgencias $sedesAgencia The sedesAgencia entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(SedesAgencias $sedesAgencia)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('sedesagencias_delete', array('idSedeAgencia' => $sedesAgencia->getIdsedeagencia())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
