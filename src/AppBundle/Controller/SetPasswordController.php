<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Entity\Usuarios;

//Auditoria
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class SetPasswordController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('cambiar contraseña');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);
          $Usuarios = new Usuarios();
          $form = $this->createForm("AppBundle\Form\setPasswordType",$Usuarios);
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){

            $usuarioActual = $em->getRepository("AppBundle:Usuarios")
            ->findBy(array("idUsuario"=>$session->get('idUsuario')));

            $newPass = $request->request->get('appbundle_usuarios')['contrasena']['second'];

            $encryptNewPass = $mainService->encryptPass($newPass);
            $usuarioActual[0]->setContrasena($encryptNewPass);

            $em->flush($usuarioActual);

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Update",
              "tabla"=>"Usuarios",
              "id_datos"=>$usuarioActual[0]->getIdUsuario(),
              "data"=>[$newPass]
            ];

            $blockchain->addBlock(new Block($dataAud));
            $blockchain->registerChain();

            return $this->render('@AppBundle/Usuarios/SetPassword.html.twig', array(
                "form" => $form->createView(),
                "mensajes"=>"Contraseña Cambiada Exitósamente."
            ));
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de cambiar contraseña",
            "id_datos"=>"vista cambiar contraseña",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Usuarios/SetPassword.html.twig', array(
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

}
