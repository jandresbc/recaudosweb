<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Parametros;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

/**
 * Parametro controller.
 *
 */
class ParametrosController extends Controller
{
    /**
     * Lists all parametro entities.
     *
     */
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('parametros');

        if($permiso === true){
          //Paginador
          $paginator = $this->get('knp_paginator');
          //Auditoria
          $blockchain = new Blockchain($em);

          $idemp = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$session->get("idSedeAgencia")]);

          $parametros = $em->getRepository('AppBundle:Parametros')->createQueryBuilder("P")
          ->orderBy("P.idParametros","DESC");

          foreach ($idemp as $key => $value) {
            $parametros->orWhere("P.idEmpresa = :idEmp".$key)
            ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
          }

          $params = $paginator->paginate(
                  $parametros->getQuery(),
                  $request->query->getInt('page', 1),
                  6
          );

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Parámetros",
            "id_datos"=>"vista parámetros - Listar",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('parametros/index.html.twig', array(
              'parametros' => $params,
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
     * Creates a new parametro entity.
     *
     */
    public function newAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('crear parametro');

        if($permiso === true){
          $parametro = new Parametros();
          //Auditoria.
          $blockchain = new Blockchain($em);
          $form = $this->createForm('AppBundle\Form\ParametrosType', $parametro);
          $form->handleRequest($request);

          if ($form->isSubmitted() && $form->isValid()) {
              $em = $this->getDoctrine()->getManager();
              $em->persist($parametro);
              $em->flush();

              //Auditoria
              $dataAud = [
                "accion"=>"Insert",
                "tabla"=>"parametros",
                "id_datos"=>$parametro->getIdparametros(),
                "data"=>$parametro->getArrayData()//Obtengo un array con los datos del objeto Entity.
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              return $this->redirectToRoute('parametros_show', array('idParametros' => $parametro->getIdparametros()));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Crear Parámetro",
            "id_datos"=>"vista crear parámetro",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('parametros/new.html.twig', array(
              'parametro' => $parametro,
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
     * Finds and displays a parametro entity.
     *
     */
    public function showAction(Request $request,Parametros $parametro)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('ver parametro');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $deleteForm = $this->createDeleteForm($parametro);

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Ver Parámetro",
            "id_datos"=>"vista ver parámetro",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('parametros/show.html.twig', array(
              'parametro' => $parametro,
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
     * Displays a form to edit an existing parametro entity.
     *
     */
    public function editAction(Request $request, Parametros $parametro)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('editar parametro');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $deleteForm = $this->createDeleteForm($parametro);
          $editForm = $this->createForm('AppBundle\Form\ParametrosType', $parametro);
          $editForm->handleRequest($request);

          if ($editForm->isSubmitted() && $editForm->isValid()) {
              $em->flush();

              //Auditoria
              $dataAud = [
                "accion"=>"Update",
                "tabla"=>"parametros",
                "id_datos"=>$parametro->getIdparametros(),
                "data"=>$parametro->getArrayData()//Obtengo un array con los datos del objeto Entity.
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              return $this->redirectToRoute('parametros_edit', array('idParametros' => $parametro->getIdparametros()));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Editar Parámetro",
            "id_datos"=>"vista editar parámetro",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('parametros/edit.html.twig', array(
              'parametro' => $parametro,
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
     * Deletes a parametro entity.
     *
     */
    public function deleteAction(Request $request, Parametros $parametro)
    {
        $form = $this->createDeleteForm($parametro);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($parametro);
            $em->flush();
        }

        return $this->redirectToRoute('parametros_index');
    }

    /**
     * Creates a form to delete a parametro entity.
     *
     * @param Parametros $parametro The parametro entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Parametros $parametro)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('parametros_delete', array('idParametros' => $parametro->getIdparametros())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
