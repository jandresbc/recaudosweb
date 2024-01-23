<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Usuarios;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

//Auditoria.
use AppBundle\Blockchain\Blockchain;
use AppBundle\Blockchain\Block;

/**
 * Usuario controller.
 *
 */
class UsuariosController extends Controller
{
    /**
     * Lists all usuario entities.
     *
     */
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('usuarios');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          //Paginador
          $paginator = $this->get('knp_paginator');

          $form = $this->createFormBuilder()
          ->add("nombreUsuario",TextType::class,array(
            "label"=>"Nombre Usuario:",
            "required"=>false,
            "attr"=>array("class"=>"form-control w-100","placeholder"=>"Nombre")
          ))->add("identificacion",NumberType::class,array(
            "label"=>"Identificación:",
            "required"=>false,
            "attr"=>array("class"=>"form-control w-100","placeholder"=>"Identificación")
          ))->getForm();

          $form->handleRequest($request);

          $usuarios = $em->getRepository('AppBundle:Usuarios')->createQueryBuilder("U")
          ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","U.idSedeAgencia=ESA.idSedeAgencia")
          ->join("AppBundle:GruposUsuarios","GU","WITH","U.idGrupoUsuario=GU.idGrupoUsuario")
          ->orderBy("U.idUsuario","DESC");

          $ESA = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy([
            "idSedeAgencia"=>$session->get("idSedeAgencia")
          ]);

          //Para el administrador solo se visualizan los usuarios que pertenezcan a su empresa.
          if($session->get("rol") == 'Administrador'){
            $usuarios->andWhere("GU.grupoUsuario <> 'Superusuario'");
            foreach ($ESA as $key => $value) {
              $usuarios->andWhere("ESA.idEmpresa = :idEmp".$key)
              ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
            }
          }else if($session->get("rol") == 'Administrador Agencias'){//Para el administrador de Agencias solo se visualizan los usuarios que pertenezcan a su agencia.
            $usuarios->join("AppBundle:SedesAgencias","SA","WITH","ESA.idSedeAgencia=SA.idSedeAgencia")
            ->andWhere("SA.idAgencia = :idAgen")
            ->andWhere("GU.grupoUsuario <> 'Superusuario'")
            ->setParameter("idAgen",$ESA[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());

            foreach ($ESA as $key => $value) {
              $usuarios->andWhere("ESA.idEmpresa = :idEmp".$key)
              ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
            }
          }

          if ($form->isSubmitted() && $form->isValid()) {
            //Filtros de busqueda en el listado.
            $nombre = $request->request->get('form')['nombreUsuario'];
            $ID = $request->request->get('form')['identificacion'];

            if($nombre != '' && $nombre != null){
              $usuarios->andWhere("U.nombreCompleto like :nombre")
              ->setParameter("nombre","%".$nombre."%");
            }

            if($ID != '' && $ID != null){
              $usuarios->andWhere("U.identificacion like :id")
              ->setParameter("id","%".$ID."%");
            }

            $users = $paginator->paginate(
                $usuarios,
                $request->query->getInt('page', 1),
                10
            );

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Usuarios",
              "id_datos"=>"vista usuarios - Lista Filtrada",
              "data"=>["cargó vista filtrada"]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            return $this->render('usuarios/index.html.twig', array(
                'usuarios' => $users,
                "form" => $form->createView()
            ));
          }

          $users = $paginator->paginate(
              $usuarios,
              $request->query->getInt('page', 1),
              10
          );

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Usuarios",
            "id_datos"=>"vista usuarios - Listar",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          $mensajesFlash = $session->getFlashBag()->get('Error');

          return $this->render('usuarios/index.html.twig', array(
              'usuarios' => $users,
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
     * Creates a new usuario entity.
     *
     */
    public function newAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('crear usuario');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $usuario = new Usuarios();
          $form = $this->createForm('AppBundle\Form\UsuariosType', $usuario);
          $form->handleRequest($request);

          if ($form->isSubmitted() && $form->isValid()) {
              $main = $this->get("main");
              $ID = $request->request->get("appbundle_usuarios")["identificacion"];
              $user = $em->getRepository("AppBundle:Usuarios")->findBy(["identificacion"=>$ID]);

              if(count($user) == 0){
                $newPass = $main->encryptPass($usuario->getContrasena());
                $usuario->setContrasena($newPass);
                $em->persist($usuario);
                $em->flush();

                //Auditoria
                $dataAud1 = [
                  "accion"=>"Insert",
                  "tabla"=>"usuarios",
                  "id_datos"=>$usuario->getIdUsuario(),
                  "data"=>$usuario->getArrayData()//Obtengo un array con los datos del objeto Entity.
                ];

                $blockchain->addBlock(new Block($dataAud1));
                $blockchain->registerChain();

                return $this->redirectToRoute('usuarios_show', array('idUsuario' => $usuario->getIdusuario()));
              }else{
                return $this->render('usuarios/new.html.twig', array(
                    'usuario' => $usuario,
                    'form' => $form->createView(),
                    'mensajes' => "El Usuario con la identificación: ".$ID.". Ya se encuentra registrado en el sistema."
                ));
              }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Crear Usuario",
            "id_datos"=>"vista crear usuario",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('usuarios/new.html.twig', array(
              'usuario' => $usuario,
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
     * Finds and displays a usuario entity.
     *
     */
    public function showAction(Request $request, Usuarios $usuario)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('ver usuario');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $deleteForm = $this->createDeleteForm($usuario);

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Ver Usuario",
            "id_datos"=>"vista ver usuario, idUsuario = ".$usuario->getIdUsuario(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('usuarios/show.html.twig', array(
              'usuario' => $usuario,
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
     * Displays a form to edit an existing usuario entity.
     *
     */
    public function editAction(Request $request, Usuarios $usuario)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('editar usuario');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $deleteForm = $this->createDeleteForm($usuario);
          $editForm = $this->createForm('AppBundle\Form\UsuariosType', $usuario);
          $editForm->handleRequest($request);

          if ($editForm->isSubmitted() && $editForm->isValid()) {
              $main = $this->get("main");
              $sqlUser = "Select contrasena From usuarios where id_usuario = ".$usuario->getIdUsuario();
              $user = $main->rawQueryDoctrine($sqlUser);
              $contra = $request->request->get("appbundle_usuarios")["contrasena"];

              if($contra != $user[0]['contrasena']){
                $newPass = $main->encryptPass($contra);
              }else{
                $newPass = $user[0]['contrasena'];
              }

              $usuario->setContrasena($newPass);

              $em->flush();//Actualiza Usuarios.

              // Auditoria
              $dataAud2 = [
                "accion"=>"Update",
                "tabla"=>"usuarios",
                "id_datos"=>$usuario->getIdUsuario(),
                "data"=>$usuario->getArrayData()//Obtengo un array con los datos del objeto Entity.
              ];

              $blockchain->addBlock(new Block($dataAud2));
              $blockchain->registerChain();

              return $this->redirectToRoute('usuarios_edit', array('idUsuario' => $usuario->getIdusuario()));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Editar Usuario",
            "id_datos"=>"vista editar usuario, idUsuario = ".$usuario->getIdUsuario(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('usuarios/edit.html.twig', array(
              'usuario' => $usuario,
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
     * Deletes a usuario entity.
     *
     */
    public function deleteAction(Request $request, Usuarios $usuario)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('eliminar usuario');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $form = $this->createDeleteForm($usuario);
          $form->handleRequest($request);

          if ($form->isSubmitted() && $form->isValid()) {
            try{
              $em->remove($usuario);

              //Auditoria.
              $dataAud2 = [
                "tabla"=>"usuarios",
                "accion"=>"Delete",
                "id_datos"=>$usuario->getIdUsuario(),
                "data"=>$usuario->getArrayData()//Se obtiene los datos del objeto Entity a eliminar.
              ];

              $blockchain->addBlock(new Block($dataAud2));
              $blockchain->registerChain();

              $em->flush();
            }catch(\Exception $e){
              $blockchain->chain = [];//Resetea la cadena.
              $this->get('session')->getFlashBag()->add(
                  'Error',
                  'Error al eliminar. Puede deberse a que el registro que intenta eliminar tenga relaciones con otros registros en otras tablas. Si ese es el motivo solo puede desactivar este usuario. '.$e->getMessage()
              );
            }
          }

          return $this->redirectToRoute('usuarios_index');
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
     * Creates a form to delete a usuario entity.
     *
     * @param Usuarios $usuario The usuario entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Usuarios $usuario)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('usuarios_delete', array('idUsuario' => $usuario->getIdusuario())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
