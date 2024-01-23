<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class TransaccionesController extends Controller
{
    public function indexAction(Request $request,$idCaja)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $mainService = $this->get("main");

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('transacciones');

        if($permiso === true){
          //Auditoria.
          $blockchain = new Blockchain($em);
          $fechaActual = new \DateTime("now",new \DateTimeZone('America/Bogota'));

          $cierres = $em->getRepository("AppBundle:Transacciones")
          ->createQueryBuilder("T")
          ->join("AppBundle:Cajas","C","WITH","T.idCaja = C.idCaja")
          ->where("T.isClosed = 0")
          ->andWhere("DATE(T.fechaHoraTransaccion) < :fechaActual")
          ->andWhere("T.idUsuario = ".$session->get('idUsuario'))
          ->setParameter("fechaActual",$fechaActual->format("Y-m-d"))
          ->getQuery()->getResult();

          $caja = $em->getRepository("AppBundle:Cajas")
          ->findBy(array("idCaja"=>$idCaja));

          if(count($cierres) == 0){
            //Modifica la ha activa la session de la caja.
            $caja[0]->setSessionActiva(1);
            //Realiza los cambios.
            $em->flush();

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Update",
              "tabla"=>"Cajas",
              "id_datos"=>$caja[0]->getIdCaja(),
              "data"=>["session_activa = 1"]
            ];

            $blockchain->addBlock(new Block($dataAud));

            //Auditoria agrega un bloque a la cadena.
            $dataAudView = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Punto de Recaudos",
              "id_datos"=>"vista punto de recaudos",
              "data"=>["cargó vista"]
            ];

            $blockchain->addBlock(new Block($dataAudView));

            //Registra los bloques de la cadena en la bd.
            $blockchain->registerChain();

            return $this->render('@AppBundle/Transacciones/transacciones.html.twig', array(
              "caja" => $caja
            ));
          }else if(count($cierres) > 0){
            $usu = $mainService->getInfoUser($session->get('identificacion'),$em);

            $cajas = $em->getRepository("AppBundle:Cajas")
            ->createQueryBuilder("C")
            ->where("C.idUsuario = :idUsu")
            ->andWhere("C.hasArchivado = 0")
            ->setParameter("idUsu",$session->get('idUsuario'))
            ->getQuery()->getResult();

            //Auditoria agrega un bloque a la cadena.
            $dataAudView = [
              "accion"=>"Load",
              "tabla"=>"Entro al módulo de Recaudos",
              "id_datos"=>"vista de recaudos",
              "data"=>["cargó vista"]
            ];

            $blockchain->addBlock(new Block($dataAudView));
            $blockchain->registerChain();

            return $this->render('@AppBundle/recaudos/recaudos.html.twig', array(
                "cajas" => $cajas,
                "mensajes" => "No puede Iniciar Recaudos porque tiene transacciones anteriores registradas, sin hacer su cierre de caja. Por favor realice el cierre de caja antes, para poder ingresar al módulo de recaudos."
            ));
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

}
