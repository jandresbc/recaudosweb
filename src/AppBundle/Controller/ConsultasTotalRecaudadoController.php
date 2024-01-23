<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Auditoria.
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class ConsultasTotalRecaudadoController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        ini_set("max_execution_time", "1200");//20 Min.
        ini_set('memory_limit', '1024M');
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('consultas total recaudado');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);

          $form = $this->createForm("AppBundle\Form\ConsultasTotalRecaudadoType");
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            //$Empresa = $request->request->get('appbundle_consultatotalreaudado')['Empresas'];
            $Municipio = $request->request->get('appbundle_consultatotalreaudado')['Municipio'];
            $Agencia = $request->request->get('appbundle_consultatotalreaudado')['Agencias'];
            $sedeAgencia = $request->request->get('appbundle_consultatotalreaudado')['sedeAgencias'];
            $fechaInicio = $request->request->get('appbundle_consultatotalreaudado')['fechaInicio'];
            $fechaFinal = $request->request->get('appbundle_consultatotalreaudado')['fechaFin'];

            //Se Valida que se seleccione algún filtro.
            if($Municipio == "" && $Agencia == '' && $sedeAgencia == "" && $fechaInicio == "" && $fechaFinal == ""){
              $mensajes = "No ha seleccionado ninguno de los filtros, por favor seleccione alguno para obtener los resultados deseados.";
              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Load",
                "tabla"=>"Entró al módulo de Consultas Total Recaudado",
                "id_datos"=>"vista consultas total recaudado - filtrado.",
                "data"=>[$mensajes]
              ];

              $blockchain->addBlock(new Block($dataAud));
              $blockchain->registerChain();

              //Retornamos a la vista del informe.
              return $this->render('@AppBundle/Consultas/consultasTotalRecaudado.html.twig', array(
                  "form" => $form->createView(),
                  "mensajes" => $mensajes
              ));
            }

            $ESAUsuario = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(
              ["idSedeAgencia"=>$session->get('idSedeAgencia')]
            );

            $pagos = $em->getRepository("AppBundle:Pagos")
            ->createQueryBuilder("P")
            ->select(array("P"))
            ->join("AppBundle:Transacciones","T","WITH","P.idTransaccion = T.idTransaccion")
            ->join("AppBundle:Facturas","F","WITH","F.idFactura = P.idFactura")
            ->join("AppBundle:Cajas","C","WITH","C.idCaja = T.idCaja")
            ->join("AppBundle:EmpresasSedesAgencias","ESA","WITH","ESA.idEmpresaSedeAgencia = T.idEmpresaSedeAgencia")
            ->join("AppBundle:SedesAgencias","SA","WITH","SA.idSedeAgencia = ESA.idSedeAgencia")
            ->join("AppBundle:Divipola","D","WITH","SA.idDivipola = D.divipola")
            ->join("AppBundle:Agencias","A","WITH","SA.idAgencia = A.idAgencia")
            ->where("P.isDeleted = 0")
            ->addGroupBy("P.idPago","T.idEmpresaSedeAgencia");

            if($session->get('rol') == 'Administrador' || $session->get('rol') == 'Auditor'){
              $pagos->andWhere("ESA.idEmpresa = :idEmp");
              $pagos->setParameter("idEmp",$ESAUsuario[0]->getIdEmpresa()->getIdEmpresa());
            }else if($session->get('rol') == 'Administrador Agencias'){
              $pagos->andWhere("SA.idAgencia = :idAgencia");
              $pagos->setParameter("idAgencia",$ESAUsuario[0]->getIdSedeAgencia()->getIdAgencia()->getIdAgencia());

              foreach ($ESAUsuario as $key => $value) {
                $pagos->andWhere("ESA.idEmpresa = :idEmp".$key);
                $pagos->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
              }
            }else if($session->get('rol') == 'Cajero'){
              $pagos->andWhere("SA.idSedeAgencia = :idSedeAgencia");
              $pagos->setParameter("idSedeAgencia",$session->get("idSedeAgencia"));

              foreach ($ESAUsuario as $key => $value) {
                $pagos->andWhere("ESA.idEmpresa = :idEmp".$key);
                $pagos->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
              }
            }

            if($Municipio != null && $Municipio != ''){
              $pagos->andWhere("SA.idDivipola = :idDiv");
              $pagos->setParameter("idDiv",$Municipio);
            }

            if($Agencia != null && $Agencia != ''){
              $pagos->andWhere("A.idAgencia = :idA");
              $pagos->setParameter("idA",$Agencia);
            }

            if($sedeAgencia != null && $sedeAgencia != ''){
              $pagos->andWhere("ESA.idEmpresaSedeAgencia = :idESA");
              $pagos->setParameter("idESA",$sedeAgencia);
            }

            if($fechaInicio != null && $fechaInicio != ''){
              $fechaIni = new \Datetime($fechaInicio);
              $pagos->andWhere("DATE(P.fechaHoraPago) >= :Inicio");
              $pagos->setParameter("Inicio",$fechaIni->format("Y-m-d"));

              if($fechaFinal == null || $fechaFinal == ''){
                $pagos->andWhere("DATE(P.fechaHoraPago) <= :Fin");
                $pagos->setParameter("Fin",new \DateTime('now',new \DateTimeZone("America\Bogota")));
              }
            }

            if($fechaFinal != null && $fechaFinal != ''){
              $fechaFin = new \Datetime($fechaFinal);
              if($fechaInicio == null || $fechaInicio == ''){
                $pagos->andWhere("DATE(P.fechaHoraPago) >= :Inicio");
                $pagos->setParameter("Inicio",new \DateTime('now',new \DateTimeZone("America\Bogota")));
              }

              $pagos->andWhere("DATE(P.fechaHoraPago) <= :Fin");
              $pagos->setParameter("Fin",$fechaFin->format("Y-m-d"));
            }
            //Fin Filtros.
            $pagos->orderBy('P.fechaHoraPago', 'DESC');
            $pagosConsulta = $pagos->getQuery()->getResult();
            //Cálculo del totalRecaudado.
            $data = array();

            $arregloARecorrer = [];

            //Recorro los resultados y almaceno los nombres de las sedes.
            foreach ($pagosConsulta as $key => $value) {
              if($Agencia != null && $Agencia != ''){//Significa que se usa como filtro la Agencia seleccionada.
                if($key == 0){
                  array_push($arregloARecorrer,$value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia());
                }

                if(!in_array($value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia(),$arregloARecorrer)){
                  array_push($arregloARecorrer,$value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia());
                }
              }else if($sedeAgencia != null && $sedeAgencia != ''){//Significa que se usa como filtro la sedeAgencia seleccionada.
                if($key == 0){
                  array_push($arregloARecorrer,$value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia());
                }

                if(!in_array($value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia(),$arregloARecorrer)){
                  array_push($arregloARecorrer,$value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia());
                }
              }else if($Municipio != null && $Municipio != ''){//Significa que se usa como filtro la Municipio seleccionada.
                if($key == 0){
                  array_push($arregloARecorrer,$value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdDivipola());
                }

                if(!in_array($value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdDivipola(),$arregloARecorrer)){
                  array_push($arregloARecorrer,$value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdDivipola());
                }
              }else{
                if($key == 0){
                  array_push($arregloARecorrer,$value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia());
                }

                if(!in_array($value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia(),$arregloARecorrer)){
                  array_push($arregloARecorrer,$value->getIdTransaccion()->getIdEmpresaSedeAgencia()->getIdSedeAgencia()->getIdAgencia());
                }
              }
            }

            //Calulo los valores a mostrar.
            foreach ($arregloARecorrer as $k => $val) {
              $nombre = "";
              if($Agencia != null && $Agencia != ''){//Significa que se usa como filtro la Agencia seleccionada.
                $nombre = trim($val->getNombreAgencia());
                $pagos->andWhere("A.nombreAgencia = :nombre")
                ->setParameter("nombre",$nombre);
              }else if($sedeAgencia != null && $sedeAgencia != ''){//Significa que se usa como filtro la sedeAgencia seleccionada.
                $nombre = trim($val->getNombreSede());
                $pagos->andWhere("SA.nombreSede = :nombre")
                ->setParameter("nombre",$nombre);
              }else if($Municipio != null && $Municipio != ''){//Significa que se usa como filtro la Municipio seleccionada.
                $nombre = trim($val->getNomPoblad());
                $pagos->andWhere("D.nomPoblad = :nombre")
                ->setParameter("nombre",$nombre);
              }else{
                $nombre = trim($val->getNombreAgencia());
                $pagos->andWhere("A.nombreAgencia = :nombre")
                ->setParameter("nombre",$nombre);
              }

              $pagosSede = $pagos->getQuery()->getResult();

              //Variable para el calculo de totales.
              $totalRecaudado = 0;
              $totalFacturasRecaudadas = 0;

              foreach ($pagosSede as $index => $valor) {
                $totalRecaudado += $valor->getVlrPago();

                if($valor->getIdFactura()->getNroFactura() != ''){
                  $totalFacturasRecaudadas++;
                }
              }

              $data[$k] = ["sedeAgencia" => $nombre,"totalRecaudado" => $totalRecaudado,"totalFacturasRecaudadas" => $totalFacturasRecaudadas];
            }

            if(count($pagosConsulta) > 0){
              if(count($data)>0){
                //Auditoria agrega un bloque a la cadena.
                $dataAud = [
                  "accion"=>"Select",
                  "tabla"=>"Pagos",
                  "id_datos"=>"vista consultas total recaudado - filtrado.",
                  "data"=>[$Municipio,$Agencia,$sedeAgencia,$fechaInicio,$fechaFinal]
                ];

                $blockchain->addBlock(new Block($dataAud));
                $blockchain->registerChain();
                //Retornamos a la vista del informe.
                return $this->render('@AppBundle/Consultas/consultasTotalRecaudado.html.twig', array(
                    "form" => $form->createView(),
                    "data" => $data,
                    "dataJson" => json_encode($data),
                ));
              }else{
                //Retornamos a la vista del informe.
                return $this->render('@AppBundle/Consultas/consultasTotalRecaudado.html.twig', array(
                    "form" => $form->createView(),
                    "mensajes" => "No ha seleccionado ninguno de los filtros, por favor seleccione alguno para obtener los resultados deseados."
                ));
              }
            }else{
                //Retornamos a la vista del informe.
                return $this->render('@AppBundle/Consultas/consultasTotalRecaudado.html.twig', array(
                    "form" => $form->createView(),
                    "mensajes" => "No hay pagos registrados en el sistema dentro de los filtros seleccionados."
                ));
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Consultas Total Recaudado",
            "id_datos"=>"vista consultas total recaudado",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Consultas/consultasTotalRecaudado.html.twig', array(
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
