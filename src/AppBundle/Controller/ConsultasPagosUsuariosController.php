<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ConsultasPagosUsuariosController extends Controller
{
    public function indexAction(Request $request,$nit)
    {
      $session = $request->getSession();
      $em = $this->getDoctrine()->getManager();
      $mainService = $this->get("main");

      //Consulto la información de la empresa según su nit
      $empresa = $em->getRepository("AppBundle:Empresas")->findBy(["nit"=>$nit]);

      if(count($empresa) > 0){
        //obtenemos la configuración de la empresa mediante el servicio.
        $conf = $mainService->ConfigappAction($empresa[0]->getIdEmpresa())->getContent();
        $configApp = json_decode($conf,true);

        $form = $this->createForm("AppBundle\Form\ConsultasPagosUsuariosType");
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
          $nroFactura = $request->request->get('appbundle_pagosusuarios')['nroFactura'];
          $matricula = $request->request->get('appbundle_pagosusuarios')['matricula'];
          $codTransaccion = $request->request->get('appbundle_pagosusuarios')['codigoTransaccion'];

          if($nroFactura != '' && $nroFactura != null || $matricula != '' && $matricula != null || $codTransaccion != '' && $codTransaccion != null){
            $transaccion = $em->createQueryBuilder()
            ->select(array("P"))
            ->from("AppBundle:Pagos","P")
            ->join("AppBundle:Transacciones","T","WITH","P.idTransaccion = T.idTransaccion")
            ->join("AppBundle:Facturas","F","WITH","P.idFactura = F.idFactura")
            ->where("P.isDeleted = 0");

            if($nroFactura != '' && $nroFactura != null){
              $transaccion->andWhere("F.nroFactura = :idFact");
              $transaccion->setParameter("idFact",$nroFactura);
            }

            if($matricula != '' && $matricula != null){
              $transaccion->andWhere("F.matricula = :mat");
              $transaccion->setParameter("mat",$matricula);
            }

            if($codTransaccion != '' && $codTransaccion != null){
              $transaccion->andWhere("T.nroTransaccion = :nroTrans");
              $transaccion->setParameter("nroTrans",$codTransaccion);
            }
            $transaccion->orderBy("P.fechaHoraPago","DESC");
            $transaccion->setMaxResults("10");//Limite de resultados a 10

            $transacciones = $transaccion->getQuery()->getResult();

            if(count($transacciones) > 0){
              return $this->render('@AppBundle/Consultas/consultasPagosUsuarios.html.twig', array(
                  "form" => $form->createView(),
                  "transacciones" => $transacciones,
                  "login" => true,//Se retorna esta variable porque se necesita para que
                  // se muestre el form sin que se inicie session.
                  "conf" => $configApp
              ));
            }else{
              return $this->render('@AppBundle/Consultas/consultasPagosUsuarios.html.twig', array(
                  "form" => $form->createView(),
                  "login" => true,//Se retorna esta variable porque se necesita para que
                  // se muestre el form sin que se inicie session.
                  "conf" => $configApp,
                  "mensajes" => "No se encontró ningún pago asociado al criterio de búsqueda."
              ));
            }
          }else{
              return $this->render('@AppBundle/Consultas/consultasPagosUsuarios.html.twig', array(
                  "form" => $form->createView(),
                  "login" => true,//Se retorna esta variable porque se necesita para que
                  // se muestre el form sin que se inicie session.
                  "conf" => $configApp,
                  "mensajes" => "Ingrese cualquiera de los campos del formulario."
              ));
          }
        }

        return $this->render('@AppBundle/Consultas/consultasPagosUsuarios.html.twig', array(
            "form" => $form->createView(),
            "login" => true,//Se retorna esta variable porque se necesita para que
            // se muestre el form sin que se inicie session.
            "conf" => $configApp
        ));
      }else{
        return new Response("Número de NIT de la empresa no se encuentra registrado en el sistema.");
      }
    }

}
