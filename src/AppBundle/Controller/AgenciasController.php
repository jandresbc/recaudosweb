<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Agencias;
use AppBundle\Entity\AgenciasEmpresas;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\Form\Extension\Core\Type\NumberType;

//Auditoria.
use AppBundle\Blockchain\Blockchain;
use AppBundle\Blockchain\Block;

/**
 * Agencia controller.
 *
 */
class AgenciasController extends Controller
{
    /**
     * Lists all agencia entities.
     *
     */
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('agencias');

        if($permiso === true){
          $blockchain = new Blockchain($em);
          //Paginador
          $paginator = $this->get('knp_paginator');

          $form = $this->createFormBuilder()
          ->add("nitAgencia",NumberType::class,array(
            "label"=>"Nit Agencia:",
            "required"=>false,
            "attr"=>array("class"=>"form-control w-100","placeholder"=>"Nit de la Agencia")
          ))->getForm();

          $form->handleRequest($request);
          $AgenciaUsuario = $em->getRepository("AppBundle:Agencias")->findBy(["nitAgencia"=>$session->get('nitAgencia')]);

          $agencias = $em->getRepository('AppBundle:Agencias')->createQueryBuilder("A")
          ->orderBy("A.idAgencia","DESC");

          //Solo se filtra por empresa si el rol es diferente de Superusuario, ya
          //que este usuario no deprende de la tabla EmpresasSedesAgencias
          if($session->get("rol") != 'Superusuario' && $session->get("rol") != 'Inactivo'){
            $AgenciasEmpresas = $em->getRepository("AppBundle:AgenciasEmpresas")->findBy(["idAgencia"=>$AgenciaUsuario[0]->getIdAgencia()]);

            $agencias->join("AppBundle:AgenciasEmpresas","AE","WITH","A.idAgencia=AE.idAgencia");
            foreach ($AgenciasEmpresas as $key => $value) {
              $agencias->orWhere("AE.idEmpresa = :idEmp".$key)
              ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
            }
          }

          if ($form->isSubmitted() && $form->isValid()) {
            //Filtros de busqueda en el listado.
            $nit = $request->request->get('form')['nitAgencia'];

            if($nit != '' && $nit != null){
              $agencias->andWhere("A.nitAgencia like :nit")
              ->setParameter("nit","%".$nit."%");
            }

            $agencias = $paginator->paginate(
                $agencias,
                $request->query->getInt('page', 1),
                10
            );

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Agencias",
              "id_datos"=>"vista agencias - Lista Filtrada",
              "data"=>["cargó vista filtrada"]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            return $this->render('agencias/index.html.twig', array(
                'agencias' => $agencias,
                "form" => $form->createView()
            ));

          }

          $agencias = $paginator->paginate(
              $agencias,
              $request->query->getInt('page', 1),
              10
          );

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Agencias",
            "id_datos"=>"vista agencias - Listar",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('agencias/index.html.twig', array(
              'agencias' => $agencias,
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
        ->validarPermiso('crear agencia');

        if($permiso === true){
          $agencia = new Agencias();
          //Auditoria.
          $blockchain = new Blockchain($em);
          $form = $this->createForm('AppBundle\Form\AgenciasType', $agencia);
          $form->handleRequest($request);

          if ($form->isSubmitted() && $form->isValid()) {
              $empresa = $request->request->get("appbundle_agencias")["agenciasEmpresas"];
              $nitAgencia = $request->request->get("appbundle_agencias")["nitAgencia"];
              $habilitarAgencia = $request->request->get("habilitarAgencia");

              $agen = $em->getRepository("AppBundle:Agencias")->findBy([
                  "nitAgencia"=>trim($nitAgencia)
              ]);

              if($habilitarAgencia == true){
                //Guardar entidad - relación AgenciasEmpresas.
                $agenciasEmpresas = new AgenciasEmpresas();
                $agenciasEmpresas->setIdAgencia($agen[0]);
                $emp = $em->getRepository("AppBundle:Empresas")->findBy(["idEmpresa"=>$empresa]);
                $agenciasEmpresas->setIdEmpresa($emp[0]);
                $em->persist($agenciasEmpresas);
                $em->flush();

                //Auditoria
                $dataAud2 = [
                  "accion"=>"Insert",
                  "tabla"=>"agencias_empresas",
                  "id_datos"=>$agenciasEmpresas->getIdAgenciaEmpresa(),
                  "data"=>$agenciasEmpresas->getArrayData()//Obtengo un array con los datos del objeto Entity.
                ];

                $blockchain->addBlock(new Block($dataAud2));
                $blockchain->registerChain();

                return $this->redirectToRoute('agencias_index');
              }else{
                //Valida si la agencia ya esta registrada.
                if(count($agen) == 0){
                  $em->persist($agencia);
                  $em->flush($agencia);

                  //Auditoria
                  $dataAud1 = [
                    "accion"=>"Insert",
                    "tabla"=>"agencias",
                    "id_datos"=>$agencia->getIdAgencia(),
                    "data"=>$agencia->getArrayData()//Obtengo un array con los datos del objeto Entity.
                  ];

                  $blockchain->addBlock(new Block($dataAud1));

                  //Guardar entidad - relación AgenciasEmpresas.
                  $agenciasEmpresas = new AgenciasEmpresas();
                  $agenciasEmpresas->setIdAgencia($agencia);
                  $emp = $em->getRepository("AppBundle:Empresas")->findBy(["idEmpresa"=>$empresa]);
                  $agenciasEmpresas->setIdEmpresa($emp[0]);
                  $em->persist($agenciasEmpresas);
                  $em->flush($agenciasEmpresas);

                  //Auditoria
                  $dataAud2 = [
                    "accion"=>"Insert",
                    "tabla"=>"agencias_empresas",
                    "id_datos"=>$agenciasEmpresas->getIdAgenciaEmpresa(),
                    "data"=>$agenciasEmpresas->getArrayData()//Obtengo un array con los datos del objeto Entity.
                  ];

                  $blockchain->addBlock(new Block($dataAud2));
                  $blockchain->registerChain();

                  return $this->redirectToRoute('agencias_show', array('idAgencia' => $agencia->getIdagencia()));
                }else{
                  $agenEmp = $em->getRepository("AppBundle:AgenciasEmpresas")->findBy([
                      "idAgencia"=>$agen[0]->getIdAgencia(),
                      "idEmpresa"=>$empresa
                  ]);

                  if(count($agenEmp) == 0){//La Agencia no esta habilitada para la empresa
                    //Auditoria agrega un bloque a la cadena.
                    $dataAud = [
                      "accion"=>"Load",
                      "tabla"=>"Entro al módulo de Crear Agencia",
                      "id_datos"=>"vista crear agencia",
                      "data"=>["cargó vista","Esta Agencia ya se encuentra registrada en el sistema, pero no está habilitada para su empresa. Para habilitarla por favor seleccioné la empresa para la cual desea activar esta agencia y luego dar click al botón 'Habilitar Agencia'."]
                    ];

                    $blockchain->addBlock(new Block($dataAud));
                    $blockchain->registerChain();

                    return $this->render('agencias/new.html.twig', array(
                        'agencia' => $agencia,
                        'form' => $form->createView(),
                        "mensajes" => "Esta Agencia ya se encuentra registrada en el sistema, pero no está habilitada para su empresa. Para habilitarla por favor seleccioné la empresa para la cual desea activar esta agencia y luego dar click al botón 'Habilitar Agencia'.",
                        "AgenciaExists" => true
                    ));
                  }else{
                    //Auditoria agrega un bloque a la cadena.
                    $dataAud = [
                      "accion"=>"Load",
                      "tabla"=>"Entro al módulo de Crear Agencia",
                      "id_datos"=>"vista crear agencia",
                      "data"=>["cargó vista","Esta Agencia ya se encuentra registrada y habilitada para su empresa, en el sistema."]
                    ];

                    $blockchain->addBlock(new Block($dataAud));
                    $blockchain->registerChain();

                    return $this->render('agencias/new.html.twig', array(
                        'agencia' => $agencia,
                        'form' => $form->createView(),
                        "mensajes" => "Esta Agencia ya se encuentra registrada y habilitada para su empresa, en el sistema."
                    ));
                  }
                }
              }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Crear Agencia",
            "id_datos"=>"vista crear agencia",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('agencias/new.html.twig', array(
              'agencia' => $agencia,
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
     * Finds and displays a agencia entity.
     *
     */
    public function showAction(Request $request, Agencias $agencia)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('ver agencia');

        if($permiso === true){
          $deleteForm = $this->createDeleteForm($agencia);
          //Auditoria.
          $blockchain = new Blockchain($em);

          //Buscamos el registro de la agencia actual.
          $agenciasEmpresas = $em->getRepository("AppBundle:AgenciasEmpresas")->findBy(["idAgencia"=>$agencia->getIdAgencia()]);
          //Seteamos el las agenciasEmpresas para la agencia actual.
          $agencia->setAgenciasEmpresas($agenciasEmpresas[0]);

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Ver Agencia",
            "id_datos"=>"vista ver agencia, idAgencia = ".$agencia->getIdAgencia(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('agencias/show.html.twig', array(
              'agencia' => $agencia,
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
     * Displays a form to edit an existing agencia entity.
     *
     */
    public function editAction(Request $request, Agencias $agencia)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('editar agencia');

        if($permiso === true){
          $deleteForm = $this->createDeleteForm($agencia);
          $editForm = $this->createForm('AppBundle\Form\AgenciasType', $agencia);
          $editForm->handleRequest($request);
          //Auditoria.
          $blockchain = new Blockchain($em);

          //Buscamos el registro de la agencia actual.
          $agenciasEmpresas = $em->getRepository("AppBundle:AgenciasEmpresas")->findBy([
            "idAgencia"=>$agencia->getIdAgencia()
          ]);
          //Seteamos las agenciasEmpresas para la agencia actual.
          $agencia->setAgenciasEmpresas($agenciasEmpresas[0]);

          //Global para seleccionar que empresa le pertenece al registro.
          //Se la utiliza en el AgenciaType.php
          $GLOBALS["empresaAgencia"] = $agenciasEmpresas[0]->getIdEmpresa()->getIdEmpresa();

          if ($editForm->isSubmitted() && $editForm->isValid()) {
              $empresa = $request->request->get("appbundle_agencias")["agenciasEmpresas"];

              $em->flush($agencia);

              //Auditoria
              $dataAud1 = [
                "accion"=>"Update",
                "tabla"=>"agencias",
                "id_datos"=>$agencia->getIdAgencia(),
                "data"=>$agencia->getArrayData()//Obtiene el arreglo de los datos del objeto Entity.
              ];

              $blockchain->addBlock(new Block($dataAud1));

              //Guardar entidad - relación AgenciasEmpresas.
              $agenciasEmpresas = $em->getRepository("AppBundle:AgenciasEmpresas")->findBy([
                "idAgencia" => $agencia->getIdAgencia(),
                "idEmpresa" => $empresa
              ]);

              $emp = $em->getRepository("AppBundle:Empresas")->findBy(["idEmpresa"=>$empresa]);
              $agenciasEmpresas[0]->setIdEmpresa($emp[0]);
              $em->flush($agenciasEmpresas);

              //Auditoria
              $dataAud2 = [
                "accion"=>"Update",
                "tabla"=>"agencias_empresas",
                "id_datos"=>$agenciasEmpresas[0]->getIdAgenciaEmpresa(),
                "data"=>$agenciasEmpresas[0]->getArrayData()//Obtengo un array con los datos del objeto Entity.
              ];

              $blockchain->addBlock(new Block($dataAud2));
              $blockchain->registerChain();

              return $this->redirectToRoute('agencias_edit', array('idAgencia' => $agencia->getIdAgencia()));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Editar Agencia",
            "id_datos"=>"vista editar agencia, idAgencia = ".$agencia->getIdAgencia(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('agencias/edit.html.twig', array(
              'agencia' => $agencia,
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
     * Deletes a agencia entity.
     *
     */
    public function deleteAction(Request $request, Agencias $agencia)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('eliminar agencia');

        if($permiso === true){
          $form = $this->createDeleteForm($agencia);
          $form->handleRequest($request);
          //Auditoria.
          $blockchain = new Blockchain($em);

          if ($form->isSubmitted() && $form->isValid()) {
              $em->remove($agencia);

              //Auditoria.
              $dataAud = [
                "tabla"=>"agencias",
                "accion"=>"Delete",
                "id_datos"=>$agencia->getIdAgencia(),
                "data"=>$agencia->getArrayData()//Se obtiene los datos del objeto Entity a eliminar.
              ];

              $blockchain->addBlock(new Block($dataAud));

              //Elimina la Relación.
              $AgenciasEmpresas = $em->getRepository("AppBundle:AgenciasEmpresas")->findBy([
                "idAgencia"=>$agencia->getIdAgencia()
              ]);

              $em->remove($AgenciasEmpresas[0]);

              //Auditoria.
              $dataAud2 = [
                "tabla"=>"agencias_empresas",
                "accion"=>"Delete",
                "id_datos"=>$AgenciasEmpresas[0]->getIdAgenciaEmpresa(),
                "data"=>$AgenciasEmpresas[0]->getArrayData()//Se obtiene los datos del objeto Entity a eliminar.
              ];

              $blockchain->addBlock(new Block($dataAud2));
              $blockchain->registerChain();

              $em->flush();
          }

          return $this->redirectToRoute('agencias_index');
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
     * Creates a form to delete a agencia entity.
     *
     * @param Agencias $agencia The agencia entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Agencias $agencia)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('agencias_delete', array('idAgencia' => $agencia->getIdagencia())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
