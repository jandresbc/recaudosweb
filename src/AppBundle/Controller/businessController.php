<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class businessController extends Controller
{

    /**
     * @Route("/businessIntelligence", name="businessIntelligence")
     *
     */
    public function indexAction(Request $request)
    {
      $session = $request->getSession();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $mainService = $this->get("main");

        $permiso = $em->getRepository('AppBundle:Permisos')->validarPermiso('business');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Business Intelligence",
            "id_datos"=>"vista de business intelligence",
            "data"=>["cargo vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/businessIntelligence/index.html.twig', []);
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
