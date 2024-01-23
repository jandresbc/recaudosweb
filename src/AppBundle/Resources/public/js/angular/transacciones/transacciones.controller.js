(function ()
{
  'use strict';

    angular.module('app.transacciones',[])
    .controller('TransaccionesController',TransaccionesController);

    /** @ngInject */
    function TransaccionesController($scope,$http,$filter, Api)
    {
        var vm = this;
        var rutaActual = Api.getAbsolutePath();
        //Filtros de Angular.
        var currencyFilter = $filter('currency');
        //variables del controller
        //Almacena todas las transacciones que serán guardadas.
        vm.threads = [{id:1,nombre:'Transacción',pagos:{facturas: [],metodoPago:'efectivo'},active:true}];
        vm.transID = 0;

        vm.connection = true;

        //init
        init();

        function init(){
            console.log("::init Controller JavaScript::");

            window.addEventListener('offline', function(){
                console.log(":: No hay internet ::");
                Api.warning("Se detectó que NO hay conexión a internet.");
                vm.connection = false;
            });

            window.addEventListener('online', function(){
                Api.alert("Ya cuenta con conexión a internet.");
                vm.connection = true;
            });

            //Evento que monitoriza cuando los threads es cambiado.
            $scope.$watch("vm.threads",function(newValue,oldValue){
               if (newValue === oldValue) {
                  return;
               }

               Api.setLocalStorage(Api.idUsuario,vm.threads,true);
            },true);

            $("#metodo").change(function(){
                vm.controlesChequesConsignacion();
            });

            //Cuando se lee un codigo de barras.
            $("#codigoBarras").change(function(){
                vm.buscarFactura("codigoBarras",$(this));
            });
        }

        vm.Init = function(){
            console.log("::Init Controller HTML::");
            Api.idUsuario = $("#idUsuario").val();

            //Si hay datos en el localStorage lo reemplaza
            //en el threads para que haya datos precargados
            //cuendo se reactive una session.
            var cache = Api.getLocalStorage(Api.idUsuario);

            if(typeof cache != undefined && cache != null && cache != ''){
                vm.threads = cache;
            }
        }

        //Registra una nueva transacción.
        vm.nuevaTransaccion = function(){
          vm.threads.push({
            id:vm.getIdTransaction('siguiente'),
            nombre:'Transacción',
            pagos:{facturas: [],metodoPago:'efectivo'},
            active:false
          });
        }

        //Elimina la transaccion del thread
        vm.eliminarTransaccion = function($index){
          if(vm.threads.length > 1){
            //Activa la transacción anterior a la que es eliminada,
            //si la transacción a eliminar es la primera se activará
            //la siguiente transacción.
            if($index > 0){//Activa la anterior transacción.
              vm.transaccionActiva($index-1);
              vm.transID = $index-1;
            }else if($index == 0){//Activa la siguiente transacción.
              vm.transaccionActiva($index+1);
              vm.transID = $index+1;
            }

            vm.threads.splice($index,1);
          }else{
            Api.warning("<b>NO</b> se permite eliminar esta transacción.");
          }
        }

        //Función que se activa esa transacción al darle click al nombre de la transacción.
        vm.transaccionActiva = function($id){
          var actual = vm.transID;//ID Actual de la transacción antes de cambiar.
          vm.transID = $id;
          //Selección por defecto como método de pago el efectivo.
          //vm.controlesChequesConsignacion('efectivo');
          vm.threads[actual].active = false;
          vm.threads[vm.transID].active = true;
          $("#codigoBarras").focus();
        }

        vm.getIdTransaction = function(opc){
          var idTrans = null;
          if(opc == 'actual'){
            idTrans = vm.threads.length - 1;
          }else if(opc == 'siguiente'){
            idTrans = vm.threads.length + 1;
          }
          return idTrans;
        }

        //Función que realiza las acciones de buscar la factura en
        //la bd y la registra en la trasaccion actual en tiempo de
        //ejecución.
        vm.buscarFactura = function(accion,Elemento){
          var idCaja = $("#idCaja").val();
          if(accion == 'codigoBarras'){
            $("#loaderCodigo").removeClass("invisible");
            var barcode = serializeCodigoBarras($(Elemento).val());
            getFactura(barcode.numeroFactura,idCaja,barcode);
            $(Elemento).val('');
          }else if(accion == 'nroFactura'){
            $("#loaderNroFactura").removeClass("invisible");
            getFactura($(Elemento).val(),idCaja);
            $(Elemento).val('');
            $("#codigoBarras").focus();
          }
        }

        //Estos son los delimitadores del codigo de barras.
        vm.delimiters = ['415','8020','3900','96'];
        //Serializa el codigo de barra para obtener sus
        //datos
        function serializeCodigoBarras(codigo){
          var serialize = null;
          var datos = {};
          datos.init = new Array();

          $.each(vm.delimiters,function(id,value){
            if(id == 0){
              var temp = codigo.split(value);
              datos.temp = temp[1];
              if(temp[0] != ''){
                datos.init.push(parseInt(temp[0]));
              }
            }else if(id > 0 ){
              var temp = datos.temp.split(value);
              datos.temp = temp[1];
              if(temp[0] != ''){
                datos.init.push(parseInt(temp[0]));
              }
            }
          });
          datos.init.push(parseInt(datos.temp));
          datos.temp = '';

          datos.barcode = {
            "codigoEmpresa" : datos.init[0],
            "numeroFactura" : datos.init[1],
            "valorFactura"  : datos.init[2],
            "fechaVencimiento" : datos.init[3]
          }

          return datos.barcode;
        }

        //Obtiene informacion de la factura desde
        //la base de datos.
        function getFactura(nroFactura,idCaja,barcode){
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          if(typeof barcode != 'undefined'){
            rutaHttp = ruta[0]+'getFactura/'+nroFactura+'/'+idCaja+"?valorFactura="+barcode.valorFactura;
          }else{
            rutaHttp = ruta[0]+'getFactura/'+nroFactura+'/'+idCaja;
          }

          // Simple GET request example:
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            // this callback will be called asynchronously
            // when the response is available
            if(jQuery.isArray(response.data)){
              $.each(response.data,function(id,value){
                if(value.status == 0){
                  Api.warning(value.error);
                  $("#loaderCodigo").addClass("invisible");
                  $("#loaderNroFactura").addClass("invisible");
                  $("#codigoBarras").focus();
                }else if(value.status == 1){
                  var fechaActual = Api.fechaActual();
                  var fechaVencFactura = Api.fechaActual(value.fechaVencimiento);

                  var fechaMostrar = value.fechaVencimiento.split(" ");

                  value.fechaVencimiento = moment(fechaMostrar[0],"YYYY/MM/DD").format("DD/MM/YYYY");

                  //Validación por fecha de vencimiento.
                  if(fechaActual <= fechaVencFactura){
                    //Validación por facturas con signo negativo.
                    if(Math.sign(value.valorFactura) != -1 && Math.sign(value.valorFactura) != -0 && !isNaN(Math.sign(value.valorFactura))){
                      vm.addCurrentThreadFact(value);
                      $("#loaderCodigo").addClass("invisible");
                      $("#loaderNroFactura").addClass("invisible");
                    }else{
                      Api.warning("El sistema <b>NO</b> permite recaudar facturas con valores negativos.");
                      $("#loaderCodigo").addClass("invisible");
                      $("#loaderNroFactura").addClass("invisible");
                      $("#codigoBarras").focus();
                    }
                  }else{
                    Api.warning("El sistema <b>NO</b> puede registrar el pago porque ésta(s) factura(s): <b>"+value.nroFactura+" | $"+value.valorFactura+"</b>, está(n) vencida(s).");
                    $("#loaderCodigo").addClass("invisible");
                    $("#loaderNroFactura").addClass("invisible");
                    $("#codigoBarras").focus();
                  }
                }
              });
            }else{
              if(response.data.status == 0){
                Api.warning(response.data.error);
                $("#loaderCodigo").addClass("invisible");
                $("#loaderNroFactura").addClass("invisible");
                $("#codigoBarras").focus();
              }
            }
          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
            $("#loaderCodigo").addClass("invisible");
            $("#loaderNroFactura").addClass("invisible");
            $("#codigoBarras").focus();
          });
        }

        //Agrega al currentThread los datos obtenidos de la factura
        vm.addCurrentThreadFact = function(data){
          if(vm.threads[vm.transID].pagos.facturas.length > 0){
            var ban = false;
            var hasData = [data.nroFactura,data.valorFactura];
            $.each(vm.threads[vm.transID].pagos.facturas,function(id,value){
              var pos = value.valorFactura.toString().indexOf(data.valorFactura);

              if( pos == -1 ){//No Existe.
                ban = false;
              }else{//Existe
                ban = true;
                return false;
              }
            });

            if(ban == false){
              var tam = vm.threads[vm.transID].pagos.facturas.length;
              vm.threads[vm.transID].pagos.facturas.unshift(data);
            }
          }else{
            data.index = 0;
            vm.threads[vm.transID].pagos.facturas.unshift(data);
          }

          //Calcula el total a Pagar por esa transaccion.
          vm.calcularTotalAPagar();
        }

        //Elimina facturas del actual thread
        vm.delCurrentThreadFact = function($index){
          if(vm.threads[vm.transID].pagos.facturas.length > 0){
            if(vm.threads[vm.transID].pagos.facturas.length == 1){
              vm.threads[vm.transID].pagos.totalAPagar = null;
              vm.threads[vm.transID].pagos.totalPagado = null;
              vm.threads[vm.transID].pagos.cambio = null;
            }

            var ind = vm.threads[vm.transID].pagos.facturas.indexOf($index);

            vm.threads[vm.transID].pagos.facturas.splice(ind,1);

            //ReCalcula el total a Pagar por esa transaccion.
            vm.calcularTotalAPagar();

            //Recalcula el cambio de la factura.
            vm.calcularCambio();

            $("#codigoBarras").focus();
          }
        }

        //Calcula el total a pagar en la transanccion.
        vm.calcularTotalAPagar = function(){
          if(vm.threads[vm.transID].pagos.facturas.length > 0){
            var total = 0;
            $.each(vm.threads[vm.transID].pagos.facturas,function(id,value){
              total += value.valorFactura;
            });

            vm.threads[vm.transID].pagos.totalAPagar = total;
          }
        }

        //Calcula el cambio que hay que devolverle al cliente.
        vm.calcularCambio = function(){
          vm.threads[vm.transID].pagos.cambio = vm.threads[vm.transID].pagos.totalPagado-vm.threads[vm.transID].pagos.totalAPagar;
        }

        //Activa los text de banco, nrocuenta,nroCheque
        var tempConsignacion = "";
        var tempCheque = "";
        var tempFechaCons = "";
        vm.controlesChequesConsignacion = function(metodoDefault=null){
          var selection = $("#metodo :selected").val();

          if(selection == 'PSE'){
            $("#banco").attr({disabled:false});
            if(tempConsignacion != ''){
              $("#nro_consignacion").append(tempConsignacion);
              tempConsignacion = '';
            }

            if(tempFechaCons != ''){
              $("#fecha_consignacion").append(tempFechaCons);
              tempFechaCons='';
            }
            $("#nroconsignacion").attr({disabled:false});
            if(tempCheque == ''){
              tempCheque = $("#nrocheque").parent().html();
            }
            $("#nrocheque").parent().empty();
            $("#fechaConsignacion").attr({disabled:false});
            $("#horaConsignacion").attr({disabled:false});

            $("#banco").val('');
            $("#nrocheque").val('');
            $("#nroconsignacion").val('');
            $("#fechaConsignacion").val('');
            $("#horaConsignacion").val('');
            $("#observaciones").val('');
          }else if(selection == 'cheque'){
            $("#banco").attr({disabled:false});

            if(tempCheque != ''){
              $("#cheques").append(tempCheque);
              tempCheque = '';
            }
            $("#nrocheque").attr({disabled:false});
            tempConsignacion = $("#nroconsignacion").parent().html();
            tempFechaCons = $("#fechaConsignacion").parent().html();
            $("#nroconsignacion").parent().empty();
            $("#fechaConsignacion").parent().empty();

            $("#banco").val('');
            $("#nrocheque").val('');
            $("#nroconsignacion").val('');
            $("#fechaConsignacion").val('');
            $("#horaConsignacion").val('');
            $("#observaciones").val('');
          }else if(selection == 'consignacion'){
            $("#banco").attr({disabled:false});
            if(tempConsignacion != ''){
              $("#nro_consignacion").append(tempConsignacion);
              tempConsignacion = '';
            }

            if(tempFechaCons != ''){
              $("#fecha_consignacion").append(tempFechaCons);
              tempFechaCons='';
            }
            $("#nroconsignacion").attr({disabled:false});
            if(tempCheque == ''){
              tempCheque = $("#nrocheque").parent().html();
            }
            $("#nrocheque").parent().empty();
            $("#fechaConsignacion").attr({disabled:false});
            $("#horaConsignacion").attr({disabled:false});

            $("#banco").val('');
            $("#nrocheque").val('');
            $("#nroconsignacion").val('');
            $("#fechaConsignacion").val('');
            $("#horaConsignacion").val('');
            $("#observaciones").val('');
          }else if(selection == 'efectivo'){
            $("#controlesChequesConsignacion").find("input").each(function(id,value){
              $(value).attr({disabled:true});
            });

            if(tempConsignacion != ''){
              $("#nro_consignacion").append(tempConsignacion);
              tempConsignacion = '';
            }
            if(tempFechaCons != ''){
              $("#fecha_consignacion").append(tempFechaCons);
              tempFechaCons = '';
            }
            if(tempCheque != ''){
              $("#cheques").append(tempCheque);
              tempCheque = '';
            }

            $("#banco").val('');
            $("#nrocheque").val('');
            $("#nroconsignacion").val('');
            $("#fechaConsignacion").val('');
            $("#horaConsignacion").val('');
            $("#observaciones").val('');
          }

          vm.threads[vm.transID].pagos.metodoPago = selection;
        }

        //Validación de los controles banco,nrocheque,nroconsignacion
        vm.hasControles = function(){
          var validacion = {};
          var status = true;

          if(vm.threads[vm.transID].pagos.metodoPago != 'efectivo'){
            $("#controlesChequesConsignacion").find("select,input").each(function(id,element){
              if($(this).attr("id") != 'horaConsignacion'){
                if ( $(this).is(':visible') && $(this).is(':disabled') == false ){
                  if( $(this).val() == '' || $(this).val() == null ){
                    status = false;
                    $(this).css({borderColor:'red'});
                  }

                  $(this).change(function(){
                    $(this).css({borderColor:'#D2D2D2'});
                  });
                }
              }
            });
          }

          if(status == false){
            validacion.status = false;
          }else if(status == true){
            validacion.status = true;
          }

          return validacion;
        }

        //Proceso de guardar la actual transacción en la base de datos.
        vm.saveTransaction = function(){
          var valControles = vm.hasControles();

          if(valControles.status == true){
            if(vm.threads[vm.transID].pagos.totalPagado != '' && vm.threads[vm.transID].pagos.totalPagado != null){
              //Validación por vm.cambio con signo negativo.
              if(vm.threads[vm.transID].pagos.totalPagado >= vm.threads[vm.transID].pagos.totalAPagar){
                  var fechaTransaccion = moment().format("YYYY-MM-DD H:m:s");
                  var ruta = Api.getAbsolutePath().split("transacciones/");

                  var tempfecha = vm.threads[vm.transID].pagos.fechaConsignacion;
                  var horaCons = vm.threads[vm.transID].pagos.horaConsignacion;

                  if(typeof tempfecha != 'undefined' && tempfecha != ''){
                    if(typeof horaCons != 'undefined' && horaCons != ''){
                      var fechaConsignacion = $("#fechaConsignacion").val()+" "+$("#horaConsignacion").val()+":00";
                    }else{
                      var fechaConsignacion = $("#fechaConsignacion").val()+" 00:00:00";
                    }
                    vm.threads[vm.transID].pagos.fechaConsignacion = fechaConsignacion;
                  }

                  $("#efectuarPago").attr({disabled:true});
                  $("#loaderEfectuarFactura").removeClass("invisible");

                  $http.post(ruta[0]+'guardarTransaccion', {
                      fechaTransaccion:fechaTransaccion,
                      idCajaActual:$("#idCaja").val(),
                      threads:vm.threads[vm.transID]
                  }).then(function successCallback(response) {
                     if(response.data.status == 'Done'){
                       var ventimp = window.open("",'',"width=380,height=660,toolbar=no,scrollbars=no");
                       ventimp.document.write(response.data.recibo);
                       ventimp.document.close();

                       //Elimina la transaccion.
                       if(vm.transID > 0){
                         vm.eliminarTransaccion(vm.transID);
                         $("#codigoBarras").focus();
                       }else if(vm.transID == 0){//Primera transacción.
                         //Si solo hay una transacción resetea el threads a los valores default.
                         vm.threads[vm.transID] = {id:1,nombre:'Transacción',pagos:{facturas: [],metodoPago:'efectivo'},active:true};
                         $("#codigoBarras").focus();
                       }

                       $("#efectuarPago").removeAttr("disabled");
                       $("#loaderEfectuarFactura").addClass("invisible");

                     }else if(response.data.status == 0){
                       Api.warning(response.data.error);
                       $("#codigoBarras").focus();

                       $("#efectuarPago").removeAttr("disabled");
                       $("#loaderEfectuarFactura").addClass("invisible");
                     }
                  }/*, function errorCallback(response) {
                    // called asynchronously if an error occurs
                    // or server returns response with an error status.
                    Api.error("Ha ocurrido un error: "+response);
                  }*/);
              }else{
                  Api.alert("<b>N0</b> se permite un valor en el <b>Total Pagado</b> menor al valor del <b>Total A Pagar</b> de la transacción.");
              }
            }else{
              Api.alert("<b>N0</b> se ha ingresado el <b>Total Pagado</b> por el Usuario.");
            }
          }else{
            if(vm.threads[vm.transID].pagos.metodoPago != 'efectivo'){
              Api.alert("Los campos con (*) son requeridos!");
            }
          }
        }

        //Proceso de guardar la actual transacción en la base de datos.
        vm.hasConsignacion = function(){
            var ruta = Api.getAbsolutePath().split("transacciones/");

            $http.post(ruta[0]+'hasConsignacion', {
                nroConsignacion:$("#nroconsignacion").val(),
                idCaja : $("#idCaja").val()
            }).then(function successCallback(response) {
               if(response.data.status == 1){
                 $("#efectuarPago").attr({disabled:false});
               }else if(response.data.status == 0){
                 Api.warning(response.data.error);
                 $("#efectuarPago").attr({disabled:true});
                 $("#codigoBarras").focus();
               }
            });
        }
    }

})();
