<?php

namespace AppBundle\Services;

//Auditoria.
use AppBundle\Blockchain\Blockchain;
use AppBundle\Blockchain\Block;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;


class ApiController extends Controller
{

  /**
   * @Route("/apiNovedadesUsuarios/{idUsuario}", name="novedadesUsuarios")
   *
   */
  public function novedadesUsuariosBIAction(Request $request,$idUsuario){
    $session = $request->getSession();
    if($session->get('auth') == 1){
      $em = $this->getDoctrine()->getManager();
      $hasNovedadesAño = $request->query->get("hasNovedadesAño");
      $año = $request->query->get("anio");
      $empresa = $request->query->get("empresa");

      if(isset($hasNovedadesAño) && isset($año) && $año != '' && $hasNovedadesAño == 'true' && $idUsuario == 'all'){
        $query = "select count(novedades.id_novedad) as totalNovedades, MONTH(novedades.fecha_hora_novedad) as mes
        FROM novedades WHERE YEAR(novedades.fecha_hora_novedad) = ".$año;

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " and novedades.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " GROUP BY MONTH(novedades.fecha_hora_novedad)";

      }else if(isset($hasNovedadesAño) && $hasNovedadesAño == 'true' && $idUsuario == 'all'){
        $query = "select count(novedades.id_novedad) as totalNovedades,
        YEAR(novedades.fecha_hora_novedad) as anio FROM novedades";

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " where novedades.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " GROUP BY YEAR(novedades.fecha_hora_novedad) Order By anio DESC limit 5";

      }else if($idUsuario != 'all'){
        $query = "Select COUNT(id_Novedad) as NumeroNovedades, nombre_completo as UsuarioSistema From novedades INNER JOIN usuarios ON usuarios.id_usuario=novedades.id_usuario Where novedades.id_usuario = ".$idUsuario;

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " and novedades.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " GROUP BY YEAR(usuarios.nombre_completo)";

      }else{
        $query = "Select COUNT(id_Novedad) as NumeroNovedades, nombre_completo as UsuarioSistema From novedades INNER JOIN usuarios ON usuarios.id_usuario=novedades.id_usuario";

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " where novedades.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " Group By novedades.id_usuario";
      }
      $novedad = $this->rawQueryDoctrine($query);
      return new JsonResponse($novedad);
    }else{
      return $this->redirectToRoute("error",
        array('codigo'=>'100')
      );
    }
  }

  /**
   * @Route("/apiGetPagos/{mes}/{anio}", name="getPagosAPI")
   *
   */
  public function getPagosBIAction(Request $request,$mes,$anio){
    $session = $request->getSession();
    if($session->get('auth') == 1){
      $em = $this->getDoctrine()->getManager();
      $hasTotalAño = $request->query->get("hasTotalAño");
      $hasTotalMes = $request->query->get("hasTotalMes");
      $hasPagosMunicipio = $request->query->get("hasPagosMunicipio");
      $empresa = $request->query->get("empresa");

      if(isset($hasPagosMunicipio) && $hasPagosMunicipio != "" && $hasPagosMunicipio == "true"){
        $query = "select ROUND(sum(pagos.vlr_pago),2) as totalRecaudado,
        divipola.nom_poblad as municipio FROM pagos
        INNER JOIN facturas ON pagos.id_factura = facturas.id_factura
        INNER JOIN transacciones ON pagos.id_transaccion = transacciones.id_transaccion
        INNER JOIN empresas_sedes_agencias ON transacciones.id_empresa_sede_agencia = empresas_sedes_agencias.id_empresa_sede_agencia
        INNER JOIN sedes_agencias ON empresas_sedes_agencias.id_sede_agencia = sedes_agencias.id_sede_agencia
        INNER JOIN agencias ON sedes_agencias.id_agencia = agencias.id_agencia
        INNER JOIN divipola ON sedes_agencias.id_divipola = divipola.divipola
        WHERE pagos.is_deleted = 0 and facturas.mes_facturado = ".$mes." and facturas.anio_facturado = ".$anio;

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " and facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " GROUP BY nom_poblad Order By municipio ASC";

      }else if(isset($hasTotalMes) && $hasTotalMes != "" && $hasTotalMes == "true"){
        $query = "Select facturas.mes_facturado as mesFacturado, ROUND(SUM(pagos.vlr_pago),2) as totalRecaudado
        From pagos INNER JOIN facturas ON facturas.id_factura=pagos.id_factura
        where pagos.is_deleted = 0 and facturas.anio_facturado = ".$anio;

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " and facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " Group By mesFacturado ORDER BY mesFacturado DESC";

      }else if(isset($hasTotalAño) && $hasTotalAño != "" && $hasTotalAño == "true"){
        $query = "Select facturas.anio_facturado as anioFacturado, ROUND(SUM(pagos.vlr_pago),2) as totalRecaudado
        From pagos INNER JOIN facturas ON facturas.id_factura=pagos.id_factura
        where pagos.is_deleted = 0";

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " and facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " Group By anioFacturado ORDER BY facturas.anio_facturado DESC limit 5";

      }else if($mes != "null" && $anio != "null"){
        $query = "Select DATE(pagos.fecha_hora_pago) as fechaPago, ROUND(SUM(pagos.vlr_pago),2) as totalRecaudoDia
        From pagos INNER JOIN facturas ON facturas.id_factura=pagos.id_factura
        where pagos.is_deleted = 0 and facturas.mes_facturado = ".$mes." and anio_facturado = ".$anio;

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " and facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " Group By DATE(pagos.fecha_hora_pago) Order By fechaPago ASC";
      }else{
        $query = "Select DATE(pagos.fecha_hora_pago) as fechaPago, ROUND(SUM(pagos.vlr_pago),2) as totalRecaudoDia
        From pagos INNER JOIN facturas ON facturas.id_factura=pagos.id_factura
        where pagos.is_deleted = 0";

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " and facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " Group By DATE(pagos.fecha_hora_pago) Order By fechaPago ASC";
      }

      $pagos = $this->rawQueryDoctrine($query);

      //Organiza los meses y el calculo de porcentaje de recaudo mes a mes.
      if(isset($hasTotalMes) && $hasTotalMes == "true"){
        sort($pagos);//Ordena un arrelgo por sus valores.
        foreach ($pagos as $key => $value) {
          if($key > 0){//El primer registro no se calcula.
            $regAnt = $key-1;
            $porc100 = $value["totalRecaudado"];
            $diffPeriodos = $value["totalRecaudado"] - $pagos[$regAnt]["totalRecaudado"];
            $porCrecimiento = ($diffPeriodos * 100)/$porc100;
            $pagos[$key]["porcentajeCrecimiento"] = round($porCrecimiento,2);
          }else{
            $pagos[$key]["porcentajeCrecimiento"] = 0;//El porcentaje de crecimiento del primer mes es del 0%
          }
        }
      }
      return new JsonResponse($pagos);
    }else{
      return $this->redirectToRoute("error",
        array('codigo'=>'100')
      );
    }
  }

  /**
   * @Route("/apiGetComportamientoUsuario/{niu}", name="getComportamientoUsuarioAPI")
   *
   */
  public function GetComportamientoUsuarioBIAction(Request $request,$niu){
    $session = $request->getSession();
    if($session->get('auth') == 1){
      $em = $this->getDoctrine()->getManager();
      $empresa = $request->query->get("empresa");

      if($niu != "all"){
        $query = "SELECT facturas.nombre_usuario, facturas.matricula, sum(pagos.vlr_pago) as totalPagado,
        sedes_agencias.nombre_sede, agencias.nombre_agencia, divipola.nom_poblad
        FROM pagos
        INNER JOIN facturas ON pagos.id_factura = facturas.id_factura
        INNER JOIN transacciones ON pagos.id_transaccion = transacciones.id_transaccion
        INNER JOIN empresas_sedes_agencias ON transacciones.id_empresa_sede_agencia = empresas_sedes_agencias.id_empresa_sede_agencia
        INNER JOIN sedes_agencias ON empresas_sedes_agencias.id_sede_agencia = sedes_agencias.id_sede_agencia
        INNER JOIN agencias ON sedes_agencias.id_agencia = agencias.id_agencia
        INNER JOIN divipola ON sedes_agencias.id_divipola = divipola.divipola
        WHERE pagos.is_deleted = 0 and facturas.matricula = ".$niu;

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " and facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " GROUP BY facturas.nombre_usuario,sedes_agencias.nombre_sede,agencias.nombre_agencia,divipola.nom_poblad,matricula";

      }else{
        $query = "SELECT facturas.nombre_usuario, facturas.matricula, sum(pagos.vlr_pago) as totalPagado,
        sedes_agencias.nombre_sede, agencias.nombre_agencia, divipola.nom_poblad
        FROM pagos
        INNER JOIN facturas ON pagos.id_factura = facturas.id_factura
        INNER JOIN transacciones ON pagos.id_transaccion = transacciones.id_transaccion
        INNER JOIN empresas_sedes_agencias ON transacciones.id_empresa_sede_agencia = empresas_sedes_agencias.id_empresa_sede_agencia
        INNER JOIN sedes_agencias ON empresas_sedes_agencias.id_sede_agencia = sedes_agencias.id_sede_agencia
        INNER JOIN agencias ON sedes_agencias.id_agencia = agencias.id_agencia
        INNER JOIN divipola ON sedes_agencias.id_divipola = divipola.divipola";

        if(isset($empresa) && $empresa != ""){
          $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
          $query .= " where facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        }

        $query .= " and pagos.is_deleted = 0 GROUP BY facturas.nombre_usuario,sedes_agencias.nombre_sede,agencias.nombre_agencia,divipola.nom_poblad,matricula";

      }

      $pagos = $this->rawQueryDoctrine($query);
      return new JsonResponse($pagos);
    }else{
      return $this->redirectToRoute("error",
        array('codigo'=>'100')
      );
    }
  }

  public function array_sort_by(&$arrIni, $col, $order = SORT_ASC)
  {
      $arrAux = array();
      foreach ($arrIni as $key=> $row)
      {
          $arrAux[$key] = is_object($row) ? $arrAux[$key] = $row->$col : $row[$col];
          $arrAux[$key] = strtolower($arrAux[$key]);
      }
      array_multisort($arrAux, $order, $arrIni);
  }

  /**
   * @Route("/apiGetPagosCartera/{anio}/{empresa}", name="getPagosCarteraAPI")
   *
   */
  public function getPagosCarteraBIAction(Request $request,$anio,$empresa){
    $session = $request->getSession();
    if($session->get('auth') == 1){
      $em = $this->getDoctrine()->getManager();
      $return = [];

      $queryTotalFacturado = "SELECT sum(facturas.valor_factura) as totalFacturado,mes_facturado,anio_facturado
      FROM facturas Where facturas.is_abono = 0 and facturas.anio_facturado = ".$anio;

      $queryTotalRecaudado = "SELECT sum(vlr_pago) as totalRecaudoGeneral, facturas.mes_facturado, facturas.anio_facturado
      FROM pagos INNER JOIN transacciones ON pagos.id_transaccion = transacciones.id_transaccion
      INNER JOIN facturas ON pagos.id_factura = facturas.id_factura
      INNER JOIN empresas_sedes_agencias ON transacciones.id_empresa_sede_agencia = empresas_sedes_agencias.id_empresa_sede_agencia
      INNER JOIN sedes_agencias ON empresas_sedes_agencias.id_sede_agencia = sedes_agencias.id_sede_agencia
      INNER JOIN empresas ON facturas.id_empresa = empresas.id_empresa AND empresas_sedes_agencias.id_empresa = empresas.id_empresa
      INNER JOIN divipola ON sedes_agencias.id_divipola = divipola.divipola
      INNER JOIN agencias ON sedes_agencias.id_agencia = agencias.id_agencia
      INNER JOIN tipo_pagos ON pagos.id_tipo_pago = tipo_pagos.id_tipo_pago
      WHERE pagos.is_deleted = 0 and facturas.anio_facturado = ".$anio;

      $queryCartera = "Select SUM(facturas.valor_factura) as totalCartera, mes_facturado, anio_facturado From facturas
      Where facturas.anio_facturado = %anio% and facturas.id_empresa = %empresa%  and facturas.is_abono = 0
      and facturas.nro_factura not in (Select fact.nro_factura from pagos INNER JOIN facturas as fact ON pagos.id_factura=fact.id_factura
      where pagos.is_deleted = 0 and fact.anio_facturado = %anio% and fact.id_empresa = %empresa% )
      GROUP BY mes_facturado,anio_facturado ORDER BY mes_facturado DESC";

      $queryPagosParciales = "Select sum(saldo) as totalPagosParciales, mes_facturado, anio_facturado from pagos INNER JOIN facturas ON pagos.id_factura=facturas.id_factura
      where facturas.anio_facturado = ".$anio." and facturas.id_empresa = %empresa% and pagos.saldo > 0 and pagos.saldo is not null and pagos.is_deleted = 0
      GROUP BY mes_facturado,anio_facturado ORDER BY mes_facturado DESC";

      $queryAvances = "Select sum(saldo) as totalAvances, mes_facturado, anio_facturado from pagos INNER JOIN facturas ON pagos.id_factura=facturas.id_factura
      where facturas.anio_facturado = ".$anio." and facturas.id_empresa = %empresa% and pagos.saldo < 0 and pagos.saldo is not null and pagos.is_deleted = 0
      GROUP BY mes_facturado,anio_facturado ORDER BY mes_facturado DESC";

      if(isset($empresa) && $empresa != ""){
        $emp = $em->getRepository("AppBundle:Empresas")->findBy(["razonSocial"=>$empresa]);
        $queryTotalFacturado .= " and facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        $queryTotalRecaudado .= " and facturas.id_empresa = ".$emp[0]->getIdEmpresa();
        $queryCartera = str_replace("%anio%",$anio,$queryCartera);
        $queryCartera = str_replace("%empresa%",$emp[0]->getIdEmpresa(),$queryCartera);
        $queryPagosParciales = str_replace("%empresa%",$emp[0]->getIdEmpresa(),$queryPagosParciales);
        $queryAvances = str_replace("%empresa%",$emp[0]->getIdEmpresa(),$queryAvances);
      }

      $queryTotalFacturado .= " GROUP BY mes_facturado, anio_facturado ORDER BY mes_facturado DESC";
      $queryTotalRecaudado .= " GROUP BY mes_facturado, anio_facturado ORDER BY mes_facturado DESC";

      $totalFacturado = $this->rawQueryDoctrine($queryTotalFacturado);
      $totalRecaudado = $this->rawQueryDoctrine($queryTotalRecaudado);
      $totalCartera = $this->rawQueryDoctrine($queryCartera);
      $totalPagosParciales = $this->rawQueryDoctrine($queryPagosParciales);
      $totalAvances = $this->rawQueryDoctrine($queryAvances);

      //Arreglar Total Recaudado. Se le resta los avances de cada mes.
      foreach ($totalRecaudado as $key => $value) {
        $findit = false;
        foreach ($totalAvances as $k => $val) {
          if($val["mes_facturado"] == $value["mes_facturado"]){
            $total = $value["totalRecaudoGeneral"] + $val["totalAvances"];//Se resta los avances. Al ser negativos se suman.
            array_push($return,[
              "totalRecaudoGeneral" => $total,
              "mes_facturado" => $value["mes_facturado"],
              "anio_facturado" => $value["anio_facturado"]
            ]);
            $findit = true;
          }
        }

        if($findit === false){
          $return[$key] = [
            "totalRecaudoGeneral" => $value["totalRecaudoGeneral"],
            "mes_facturado" => $value["mes_facturado"],
            "anio_facturado" => $value["anio_facturado"]
          ];
        }
      }

      //Arreglar Cartera. Se le suma los pagos parciales de cada mes.
      foreach ($totalCartera as $key => $value) {
        $ban = false;
        foreach ($totalPagosParciales as $k => $val){
          $i = array_search($value["mes_facturado"],$val);
          if($i != false){
            $total = $value["totalCartera"] + $val["totalPagosParciales"];//Se suma los pagos parciales a los abonos.

            foreach ($return as $ky => $v) {
              $index = array_search($value["mes_facturado"],$v);
              //if($index != false){
              if($value["mes_facturado"] == $v["mes_facturado"]){
                $return[$ky]["totalCartera"] = $total;
                $ban = true;
              }
            }
          }
        }

        if($ban == false){
          $return[$key]["totalCartera"] = $value["totalCartera"];
        }

      }

      //Agregar el totalFacturado a los datos.
      foreach ($totalFacturado as $key => $value) {
        foreach ($return as $k => $val) {
          $i = array_search($value["mes_facturado"],$val);
          if($i != false){
            $return[$k]["totalFacturado"] = $totalFacturado[$key]["totalFacturado"];
          }
        }
      }

      $this->array_sort_by($return,'mes_facturado');//Ordena el arreglo.
      //var_dump($return);

      return new JsonResponse($return);
    }else{
      return $this->redirectToRoute("error",
        array('codigo'=>'100')
      );
    }
  }

  /**
   * @Route("/apiGetPeriodos", name="getPeriodosAPI")
   *
   */
  //Función que consulta todos los periodos registrados en facturas
  //de las empresas del usuario actual.
  public function getPeriodosBIAction(Request $request)
  {
    //Variables
    $em = $this->getDoctrine()->getManager();
    $session = new Session();

    if($session->get("auth") == 1){//Valida si hay una sesion activa en el navegador.
      $idemp = $em->getRepository("AppBundle:EmpresasSedesAgencias")->findBy(["idSedeAgencia"=>$session->get("idSedeAgencia")]);
      $query = "Select DISTINCT mes_facturado, anio_facturado, razon_social as empresa From facturas INNER JOIN empresas ON empresas.id_empresa=facturas.id_empresa where ";
      $return = [];

      $meses = [1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];

      foreach ($idemp as $key => $value) {
        if($key > 0){
          $query .= " OR facturas.id_empresa = ".$value->getIdEmpresa()->getIdEmpresa();
        }else if($key == 0){
          $query .= " facturas.id_empresa = ".$value->getIdEmpresa()->getIdEmpresa();
        }
      }

      $facturas = $this->rawQueryDoctrine($query);

      foreach ($facturas as $k => $v) {
        $keys = array_keys($return);

        if(in_array($v["empresa"],$keys)){
          array_push($return[$v["empresa"]]["periodosFacturacion"],[
            "mes_facturado" => $v["mes_facturado"],
            "text_mes_facturado" => $meses[$v["mes_facturado"]],
            "anio_facturado" => $v["anio_facturado"]
          ]);
        }else{
          $return[$v["empresa"]] = ["periodosFacturacion"=>[]];
          $return[$v["empresa"]]["periodosFacturacion"][0] = [
            "mes_facturado" => $v["mes_facturado"],
            "text_mes_facturado" => $meses[$v["mes_facturado"]],
            "anio_facturado" => $v["anio_facturado"]
          ];
        }
      }

      return new JsonResponse($return);
    }else{
      return $this->redirectToRoute("error",
        array('codigo'=>'100')
      );
    }//Fin validación de autenticación
  }

  //Ejecuta las sentencias de raw query en doctrine.
  private function rawQueryDoctrine($query,$params = [],$type = "select"){
    $em = $this->getDoctrine()->getManager();
    $db = $em->getConnection();
    $return = "";

    $stmt = $db->prepare($query);
    $rowAffected = $stmt->execute($params);

    if($type == "select"){
      $return = $stmt->fetchAll();
    }else{
      $return = $rowAffected;
    }

    return $return;
  }
}
