<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Entity\Transacciones;
use AppBundle\Entity\Pagos;
use AppBundle\Entity\CierresDeCajas;
use AppBundle\Entity\CierresDeCajasTransacciones;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class CruceDeCuentasController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('cruce de cuentas');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);
          $form = $this->createForm("AppBundle\Form\CruceDeCuentasType");
          $form->handleRequest($request);

          if($form->isSubmitted()){
            $caja = $request->request->get("appbundle_crucedecuentas")['cajas'];
            $banco = $request->request->get("appbundle_crucedecuentas_banco");
            $fechaConsignacion = $request->request->get("appbundle_crucedecuentas_fechaConsignacion");
            $horaConsignacion = $request->request->get("appbundle_crucedecuentas_horaConsignacion");
            $nroConsignacion = $request->request->get("appbundle_crucedecuentas_nroConsignacion");
            $nroCheque = $request->request->get("appbundle_crucedecuentas_nroCheque");
            $factura = $request->request->get("appbundle_crucedecuentas")['facturas'];
            $valorCruce = $request->request->get("appbundle_crucedecuentas")['valorCruce'];
            $observaciones = $request->request->get("appbundle_crucedecuentas")['observaciones'];

            if($factura == ''){
              $mensaje = "Antes de continuar consulte y seleccione la factura de la cual desea generar el cruce de cuentas.";

              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Load",
                "tabla"=>"Entro al módulo de Cruce de Cuentas",
                "id_datos"=>"vista cruce de cuentas",
                "data"=>["cargó vista",$mensaje]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              return $this->render('@AppBundle/CruceDeCuentas/CruceDeCuentas.html.twig', array(
                  "form" => $form->createView(),
                  'Error' => $mensaje
              ));
            }

            if($caja != ''){
              $cajas = $em->getRepository("AppBundle:Cajas")->findBy([
                "idCaja"=>$caja
              ]);
            }else if($caja == ''){
              //Se consulta la caja de acuerdo al id que se desea hacer el cierre.
              $cajas = $em->getRepository("AppBundle:Cajas")->findBy([
                "idUsuario"=>$session->get('idUsuario'),
                "hasArchivado"=>0 //La caja no esté archivada
              ]);
            }

            //Inicia la transacción de los registros en la bd.
            $em->getConnection()->beginTransaction();

            try{
              $totalAPagar = $valorCruce;
              $dateNow = new  \DateTime("now",new \DateTimeZone('America/Bogota'));
              //Registrar una nueva transaccion.
              $transaccion = new Transacciones();
              $transaccion->setNroTransaccion($mainService->getNroTransaction(7));
              $transaccion->setFechaHoraTransaccion($dateNow);
              $codigoSeguridad = $mainService->getCodeSecurity(array(
                "fechaTransaccion"=>$dateNow->format('Y-m-d H:i:s'),
                "totalTransaccion"=>$totalAPagar,
                "idUsuario"=>trim($session->get('idUsuario')),
                "idCaja"=>$cajas[0]->getIdCaja(),
                "idEmpresaSedeAgencia" => trim($cajas[0]->getIdEmpresaSedeAgencia()->getIdEmpresaSedeAgencia())
              ));
              $transaccion->setCodigoSeguridad($codigoSeguridad);
              $transaccion->setTotalTransaccion($totalAPagar);
              $transaccion->setIdUsuario($cajas[0]->getIdUsuario());
              $transaccion->setIdCaja($cajas[0]);
              $transaccion->setIdEmpresaSedeAgencia($cajas[0]->getIdEmpresaSedeAgencia());

              //Guardamos los datos de la transacción en la bd.
              $em->persist($transaccion);
              $em->flush($transaccion);

              //Auditoria agrega un bloque a la cadena.
              $dataAudTrans = [
                "accion"=>"Insert",
                "tabla"=>"transacciones",
                "id_datos"=>$transaccion->getIdTransaccion(),
                "data"=>$transaccion->getArrayData()
              ];

              $blockchain->addBlock(new Block($dataAudTrans));
              //Fin registrar transacción.

              //Registra los pagos de la transaccion
              $pago = new Pagos();
              $pago->setVlrPago($totalAPagar);
              $pago->setFechaHoraPago($dateNow);
              $pago->setObservaciones($observaciones);

              if($banco != ''){
                $pago->setBanco($banco);
              }

              if($fechaConsignacion != '' && $horaConsignacion != ''){
                $pago->setFechaConsignacion(new \DateTime($fechaConsignacion." ".$horaConsignacion,new \DateTimeZone("America/Bogota")));
              }

              if($nroConsignacion != ''){
                $pago->setNroConsignacion($nroConsignacion);
              }

              if($nroCheque != ''){
                $pago->setNroCheque($nroCheque);
              }

              // Consulta de la factura de la cual se registra el pago.
              $facturaCruce = $em->getRepository("AppBundle:Facturas")
              ->findBy(["idFactura"=>$factura]);

              $pago->setIdFactura($facturaCruce[0]);
              $pago->setIdTransaccion($transaccion);
              //consulta el metodo de pago para ser seteado.
              $metodoPago = $em->getRepository("AppBundle:MetodosPago")
              ->findBy(["metodoPago"=>"Cruce de Cuentas"]);
              $pago->setIdMetodoPago($metodoPago[0]);
              //Busca el tipo pago con el id 1 = Total
              $tipoPago = $em->getRepository("AppBundle:TipoPagos")->findBy(["idTipoPago"=>1]);
              //Setea el id del tipo de pago.
              $pago->setIdTipoPago($tipoPago[0]);

              //Guardamos los datos del pago.
              $em->persist($pago);
              $em->flush($pago);

              //Auditoria agrega un bloque a la cadena.
              $dataAudPagos = [
                "accion"=>"Insert",
                "tabla"=>"pagos",
                "id_datos"=>$pago->getIdPago(),
                "data"=>$pago->getArrayData()
              ];

              $blockchain->addBlock(new Block($dataAudPagos));
              //Fin pagos.

              //Setea la factura actual para quitarla del
              //periodo actual de facturación.
              $facturaCruce[0]->setPeriodoActual(0);
              $em->flush($facturaCruce[0]);

              //Auditoria agrega un bloque a la cadena.
              $dataAudFact = [
                "accion"=>"Update",
                "tabla"=>"facturas",
                "id_datos"=>$facturaCruce[0]->getIdFactura(),
                "data"=>["periodoActual = 0"]
              ];

              $blockchain->addBlock(new Block($dataAudFact));

              //Activa la session de la caja.
              //Modifica la ha activa la session de la caja.
              if($cajas[0]->getSessionActiva() == 0){
                $cajas[0]->setSessionActiva(1);
                //Realiza los cambios.
                $em->flush($cajas[0]);

                //Auditoria agrega un bloque a la cadena.
                $dataAudCaja = [
                  "accion"=>"Update",
                  "tabla"=>"cajas",
                  "id_datos"=>$cajas[0]->getIdCaja(),
                  "data"=>["session_activa = 1"]
                ];

                $blockchain->addBlock(new Block($dataAudCaja));
              }
              //Fin activación de la caja.

              //Registra la transacción de registros en la bd.
              $em->getConnection()->commit();

              $blockchain->registerChain();

              return $this->redirectToRoute("CruceDeCuentas",["mensajes"=>"Cruce de Cuentas Realizado Existósamente"]);

            }catch(Exception $e){
              $blockchain->chain = []; //Elimina datos de la cadena en caso de error.
              $this->get('session')->getFlashBag()->add(
                  'Error',
                  'Error al procesar el cruce de cuentas. '.$e->getMessage()
              );
              $em->getConnection()->rollback();
               throw $e;
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Cruce de Cuentas",
            "id_datos"=>"vista cruce de cuentas",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          $mensajesFlash = $session->getFlashBag()->get('Error');

          return $this->render('@AppBundle/CruceDeCuentas/CruceDeCuentas.html.twig', array(
              "form" => $form->createView(),
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

}
