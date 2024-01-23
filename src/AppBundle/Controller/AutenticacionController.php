<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Usuarios;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\ParameterBag;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class AutenticacionController extends Controller
{
    private $confapp = null;
    private $mensajes = null;

    public function indexAction(Request $request)
    {
        //Instancias de los objetos a utilizar.
        $usuario = new Usuarios();
        $em = $this->getDoctrine()->getManager();
        $main = $this->get("main");
        $session = $request->getSession();

        //Se crea el formulario de login
        $formLogin = $this->createFormBuilder($usuario)
        ->add('identificacion',null,array('label'=>'Identificación: ','attr'=>array('class'=>'form-control','autofocus'=>true)))
        ->add('contrasena',PasswordType::class,array('label'=>'Clave de Ingreso: ','attr'=>array('class'=>'form-control')))
        ->getForm();

        $formLogin->handleRequest($request);

        if($formLogin->isSubmitted() && $formLogin->isValid()){
            //Auditoria.
            $blockchain = new Blockchain($em);
            //Consultar información del Usuario que se desea logear.
            $infUs = $main->getInfoUser($request->request->get('form')['identificacion'],$em);

            if(count($infUs) > 0){
              //Encripta el password recibido por el request
              //desde el formulario de inicio de sesion.
              $password = $main->encryptPass($request->request->get('form')['contrasena']);

              $ESA = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$infUs[0]->getIdSedeAgencia()->getIdSedeAgencia()]);
              $this->confapp = $em->getRepository("AppBundle:Parametros")->findBy(["idEmpresa"=>$ESA[0]->getIdEmpresa()->getIdEmpresa()]);

              if(trim($password) === trim($infUs[0]->getContrasena()) && trim($usuario->getIdentificacion()) === trim($infUs[0]->getIdentificacion())){
                  $session = new Session();
                  $session->invalidate();//Elimina la session si esta esta activa.
                  $session->start();//Inicia la sesion.

                  //Determina el mes y año del periodo Actual.
                  $now = new \Datetime('now', new \DateTimeZone('America/Bogota'));
                  $mesPeriodoActual = ltrim($now->format("m"),0)-1 == 0 ? 12 : ltrim($now->format("m"),0)-1;
                  $añoPeriodoActual = $mesPeriodoActual == 12 ? $now->format("Y")-1 : $now->format("Y");
                  $session->set('mesPeriodoActual',$mesPeriodoActual);
                  $session->set('anioPeriodoActual',$añoPeriodoActual);
                  //Fin mes y año del periodo Actual.

                  $empresas = $em->getRepository("AppBundle:Empresas")->createQueryBuilder("E")
                  ->getQuery()->getArrayResult();
                  $nitEmpresas=[];
                  foreach ($empresas as $key => $value) {
                    array_push($nitEmpresas,$value["nit"]);
                  }

                  //Setear las variables de sesion para la aplicación.
                  $session->set('configuracionapps',$this->confapp);
                  $session->set('auth','1');
                  $session->set('identificacion',$infUs[0]->getIdentificacion());
                  $session->set('rol',$infUs[1]->getGrupoUsuario());
                  $session->set('idTipoRol',$infUs[1]->getIdGrupoUsuario());
                  $session->set('idUsuario',$infUs[0]->getIdUsuario());
                  $session->set('nombreUsuario',$infUs[0]->getNombreCompleto());
                  $session->set('idSedeAgencia',$infUs[0]->getIdSedeAgencia()->getIdSedeAgencia());
                  $session->set('empresas',$empresas);
                  $session->set('nitEmpresas',$nitEmpresas);
                  $session->set("nitAgencia",$infUs[0]->getIdSedeAgencia()->getIdAgencia()->getNitAgencia());
                  $session->set('urllogout',$this->generateUrl('logout',
                    array(
                      'usuario'=>$infUs[0]->getIdentificacion()
                  )));

                  //Actualiza la base de datos en la tabla usuario en el campo auth,
                  //para identificar que usuario esta autenticado en el sistema.
                  $us = $em->getRepository('AppBundle:Usuarios')->findBy(
                    array(
                      'identificacion'=>$infUs[0]->getIdentificacion()
                    )
                  );
                  //print_r($us);
                  $us[0]->setAuth('1');
                  //persistimos el objeto.
                  $em->persist($us[0]);
                  //Actualizamos la base de datos.
                  $em->flush($us[0]);

                  //Auditoria agrega un bloque a la cadena.
                  $dataAud = [
                    "accion"=>"Autentication",
                    "tabla"=>"Usuario se autenticó en el sistema",
                    "id_datos"=>"Autenticación usuario",
                    "data"=>["Inicio de Sesión"]
                  ];

                  $blockchain->addBlock(new Block($dataAud));
                  $blockchain->registerChain();

                  return $this->redirectToRoute('dashboard');
              }else{
                $this->mensajes = "Ocurrio un error al iniciar sesión en el sistema. Verifique su ID y contraseña e intente nuevamente.";
              }
            }else{
              $this->mensajes = "Hay un error al iniciar sesion. Por favor revise su ID y contraseña y vuelva a intentarlo. Si sigue sin poder Iniciar Sesión es posible que su usuario, Sede o Agencia esten Inactivas, para resolver este problema contacte al Administrador del Sistema.";
            }
        }

        if($session->get("auth") == 1){//Tiene una session iniciada.
          //Redirege al usuario al dashboard.
          return $this->redirectToRoute('dashboard', array());
        }else{
          return $this->render('@AppBundle/auth/auth.html.twig', array(
              'formlogin' => $formLogin->createView(),
              'mensajes_sistema' => $this->mensajes,
              'login'=>true,
          ));
        }
    }

    public function logoutAction(Request $request,$usuario)
    {
        //Instancia los objetos.
        $session = $request->getSession();
        $em = $this->getDoctrine()->getManager();
        $main = $this->get('main');
        //Auditoria.
        $blockchain = new Blockchain($em);

        //Consultar información del Usuario que se desea logear.
        $infUs = $main->getInfoUser($usuario,$em);

        //Actualiza la base de datos en la tabla usuario en el campo auth,
        //para identificar que usuario esta autenticado en el sistema.
        $us = $em->getRepository('AppBundle:Usuarios')->findBy(
          array(
            'identificacion'=>$infUs[0]->getIdentificacion()
          )
        );
        //print_r($us);
        $us[0]->setAuth('0');
        //persistimos el objeto.
        $em->persist($us[0]);
        //Actualizamos la base de datos.
        $em->flush($us[0]);

        //Auditoria agrega un bloque a la cadena.
        $dataAud = [
          "accion"=>"Logout",
          "tabla"=>"El usuario cerró sesión",
          "id_datos"=>"Cierre de sesión",
          "data"=>["cierre del sistema"]
        ];

        $blockchain->addBlock(new Block($dataAud));
        $blockchain->registerChain();

        //Eliminar la sesion.
        $session->invalidate();

        //Redirigir al Route.
        return $this->redirectToRoute("auth");
    }

}
