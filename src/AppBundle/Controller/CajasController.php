<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\TextType;

use AppBundle\Entity\Cajas;

//Auditoria
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class CajasController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('crear caja');

        if($permiso === true){
          $mainService = $this->get('main');
          $Cajas = new Cajas();
          //Auditoria.
          $blockchain = new Blockchain($em);
          $form = $this->createForm("AppBundle\Form\CajasType",$Cajas);
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $em->getConnection()->beginTransaction();

            try{
              $now = new \DateTime('now', new \DateTimeZone("America/Bogota"));
              $Cajas->setFechaHoraCreacion($now);

              $em->persist($Cajas);
              $em->flush($Cajas);

              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Insert",
                "tabla"=>"Cajas",
                "id_datos"=>$Cajas->getIdCaja(),
                "data"=>$Cajas->getArrayData()
              ];

              $blockchain->addBlock(new Block($dataAud));

              $em->getConnection()->commit();

              //Registra los bloques de la cadena en la bd.
              $blockchain->registerChain();

              //Redirecciona a recaudos.
              return $this->redirectToRoute('recaudos');
            }catch(Exception $e){
                $blockchain->chain = [];//Reinicializa la cadena en caso de un error.
                $em->getConnection()->rollback();
                throw $e;
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Crear Cajas",
            "id_datos"=>"vista crear cajas",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Cajas/Cajas.html.twig', array(
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

    public function ArchivarCajaAction(Request $request, $idCaja)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $mainService = $this->get("main");
        //Auditoria.
        $blockchain = new Blockchain($em);

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('archivar caja');

        if($permiso === true){
          $em->getConnection()->beginTransaction();

          try{
            $fechaActual = new \DateTime("now",new \DateTimeZone('America/Bogota'));

            $caja = $em->getRepository("AppBundle:Cajas")
            ->findBy(array(
              "idCaja"=>$idCaja
            ));

            $cierres = $em->getRepository("AppBundle:Transacciones")
            ->createQueryBuilder("T")
            ->join("AppBundle:Cajas","C","WITH","T.idCaja = C.idCaja")
            ->where("T.isClosed = 0")
            ->andWhere("DATE(T.fechaHoraTransaccion) <= :fechaActual")
            ->andWhere("T.idUsuario = ".$session->get('idUsuario'))
            ->andWhere("T.idCaja = ".$idCaja)
            ->setParameter("fechaActual",$fechaActual->format("Y-m-d"))
            ->getQuery()->getResult();

            if(count($cierres) == 0){
              $cajaTrans = $em->getRepository("AppBundle:Cajas")
              ->createQueryBuilder("C")
              ->join("AppBundle:Transacciones","T","WITH","C.idCaja=T.idCaja")
              ->where("C.idCaja = :idC")
              ->setParameter("idC",$idCaja)
              ->getQuery()->getResult();

              //Si la caja tiene pagos se archiva
              if(count($cajaTrans) > 0){
                //Setea la caja para archivarla.
                $caja[0]->setHasArchivado(1);

                //modifico la caja para archivarla.
                $em->flush($caja);

                //Auditoria agrega un bloque a la cadena.
                $dataAud = [
                  "accion"=>"Update",
                  "tabla"=>"Cajas",
                  "id_datos"=>$caja[0]->getIdCaja(),
                  "data"=>["hasArchivado = 1"]
                ];

                $blockchain->addBlock(new Block($dataAud));

              }else{//Sino se elimina la caja.
                //Elimina la cajas
                $em->remove($caja[0]);

                //Auditoria agrega un bloque a la cadena.
                $dataAud = [
                  "accion"=>"Delete",
                  "tabla"=>"Cajas",
                  "id_datos"=>$caja[0]->getIdCaja(),
                  "data"=>$caja[0]->getArrayData()
                ];

                $blockchain->addBlock(new Block($dataAud));
                //Elimina la caja de la tabla.
                $em->flush($caja);
              }

              $em->getConnection()->commit();

              //Registra los bloques de la cadena en la bd.
              $blockchain->registerChain();

              $admin = $request->query->get("admin");
              if($admin == "true"){
                //Redirecciona a recaudos.
                return $this->redirectToRoute('historialCajas');
              }else{
                //Redirecciona a recaudos.
                return $this->redirectToRoute('recaudos');
              }
            }else if(count($cierres) > 0){
              $usu = $mainService->getInfoUser($session->get('identificacion'),$em);

              $cajas = $em->getRepository("AppBundle:Cajas")
              ->createQueryBuilder("C")
              ->where("C.idUsuario = :idUsu")
              ->andWhere("C.hasArchivado = 0")
              ->setParameter("idUsu",$session->get('idUsuario'))
              ->getQuery()->getResult();

              return $this->render('@AppBundle/recaudos/recaudos.html.twig', array(
                  "cajas" => $cajas,
                  "mensajes" => "No puede Archivar / Eliminar esta caja porque tiene transacciones registradas, sin hacer su cierre de caja. Por favor si quiere continuar con esta acción, realice antes el cierre de caja respectivo."
              ));
            }
          }catch(Exception $e){
              $blockchain->chain = []; //Reinicializa la cadena en caso de error.
              $em->getConnection()->rollback();
              throw $e;
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

    public function desArchivarAction(Request $request, $idCaja)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();
        $mainService = $this->get("main");
        //Auditoria.
        $blockchain = new Blockchain($em);

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('desarchivar caja');

        if($permiso === true){
          $em->getConnection()->beginTransaction();

          try{
            $caja = $em->getRepository("AppBundle:Cajas")->findBy(["idCaja"=>$idCaja]);

            //Setea la caja para archivarla.
            $caja[0]->setHasArchivado(0);

            //modifico la caja para archivarla.
            $em->flush($caja);

            //Auditoria agrega un bloque a la cadena.
            $dataAud = [
              "accion"=>"Update",
              "tabla"=>"Cajas",
              "id_datos"=>$caja[0]->getIdCaja(),
              "data"=>["hasArchivado = 0"]
            ];

            $blockchain->addBlock(new Block($dataAud));

            $em->getConnection()->commit();

            //Registra los bloques de la cadena en la bd.
            $blockchain->registerChain();

            //Redirecciona a recaudos.
            return $this->redirectToRoute('historialCajas');

          }catch(Exception $e){
              $blockchain->chain = []; //Reinicializa la cadena en caso de error.
              $em->getConnection()->rollback();
              throw $e;
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

    public function editAction(Request $request,Cajas $Caja)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('editar caja');

        if($permiso === true){
          $mainService = $this->get('main');
          //$Cajas = new Cajas();
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createFormBuilder()
          ->add('nombreCaja',TextType::class,array(
            "label"=>"Titulo de la Caja *",
            "attr"=>array("class"=>"form-control w-50")
          ))->getForm();

          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $em->getConnection()->beginTransaction();

            try{
              $nombre = $request->request->get("form")["nombreCaja"];

              $Caja->setNombreCaja($nombre);

              $em->flush($Caja);

              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Update",
                "tabla"=>"Cajas",
                "id_datos"=>$Caja->getIdCaja(),
                "data"=>$Caja->getArrayData()
              ];

              $blockchain->addBlock(new Block($dataAud));

              $em->getConnection()->commit();

              //Registra los bloques de la cadena en la bd.
              $blockchain->registerChain();

              //Redirecciona a recaudos.
              return $this->redirectToRoute('historialCajas');
            }catch(Exception $e){
              $blockchain->chain = [];//Reinicializa la cadena en caso de un error.
              $em->getConnection()->rollback();
              throw $e;
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Editar Caja",
            "id_datos"=>"vista editar caja, idCaja = ".$Caja->getIdCaja(),
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Cajas/EditarCaja.html.twig', array(
              "form" => $form->createView(),
              "caja" => $Caja
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
