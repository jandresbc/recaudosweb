(function ()
{
    'use strict';

    angular.module('app.novedades',['ngMaterial','ngAnimate','ngMessages']).controller('NovedadesController',NovedadesController);

    /** @ngInject */
    function NovedadesController($scope,$http,$filter, Api)
    {
        var vm = this;

        //variables del controller
        vm.loader = false;
        vm.formdata = [];
        vm.data = [];
        vm.modulo = null;
        vm.nroFactura = null;

        vm.connection = true;

        function Init(){
            console.log(":: Init NovedadesController JavaScript ::");

            window.addEventListener('offline', function(){
                Api.warning("Se detectó que NO hay conexión a internet.");
                vm.connection = false;
            });

            window.addEventListener('online', function(){
                Api.alert("Ya cuenta con conexión a internet.");
                vm.connection = true;
            });
        }

        //Init
        Init();

        //Obtiene los datos de pagos según el nro de la factura ingresada.
        vm.getInfoPagos = function(){
          console.log(":: Get Payments Data ::");
          var rutaHttp = "";
          var ruta = Api.getAbsolutePath().replace("/novedades","").replace("/new","");
          var rutaHttp = ruta+"getInfoPagos/"+vm.nroFactura;
          vm.loader = true;//Muestra el loader en pantalla.

          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            if(response.data.length > 0){
              reinit();
              vm.data = response.data;
            }else if(response.data.status == false){
              reinit();
              alert(response.data.error);
              vm.data = [];
            }
          }, function errorCallback(response) {
            reinit();
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        // Función para reiniciar todas las variables del controller.
        function reinit(){
          vm.loader = false;
          vm.formdata = [];
          vm.data = [];
        }

        //Para mostrar el formulario de eliminar y registrar la novedad.
        vm.eliminar = function(id){
          if(vm.modulo != null){
            $("#appbundle_novedades_moduloAfectado").css({borderColor:"#b2bec3"});
            vm.formdata[id] = {};
            vm.formdata[id].eliminar = true;
          }else{
            alert("Debe seleccionar el módulo ha afectar primero antes de realizar esta acción.");
            $("#appbundle_novedades_moduloAfectado").css({borderColor:"#eb2f06"});
          }
        }

        //Para mostrar el formulario de modificar y cargar los datos permitidos de modificación.
        vm.modificar = function(id){
          if(vm.modulo != null){
            if(vm.modulo == 'pagos'){
              $("#appbundle_novedades_moduloAfectado").css({borderColor:"#b2bec3"});
              vm.formdata[id] = {};
              vm.formdata[id].datosModificar = true;
              if(vm.data[id].banco != null && typeof vm.data[id].banco != 'undefined'){
                vm.formdata[id].banco = vm.data[id].banco.toLowerCase();
              }

              if(vm.data[id].fecha_consignacion != null || typeof vm.data[id].fecha_consignacion != 'undefined'){
                vm.formdata[id].fecha_consig = new Date(vm.data[id].fecha_consignacion);
                vm.formdata[id].tiempo_consignacion = new Date(vm.data[id].fecha_consignacion);
              }
              vm.formdata[id].nro_consignacion = vm.data[id].nro_consignacion;
              vm.formdata[id].nro_cheque = vm.data[id].nro_cheque;
              vm.formdata[id].observaciones = vm.data[id].observaciones;
              vm.formdata[id].id_metodo_pago = vm.data[id].id_metodo_pago;
            }
          }else{
            alert("Debe seleccionar el módulo ha afectar primero antes de realizar esta acción.");
            $("#appbundle_novedades_moduloAfectado").css({borderColor:"#eb2f06"});
          }
        }

        //Ejecuta los procesos de eliminación y/o modificación sobre los módulos.
        vm.EjecutarAccion = function(id,idRegistro,accion){
          if(vm.modulo != null){
            if(vm.formdata[id].observaciones_novedad != null){
              var $accion = '';
              accion == 'modificar' ? $accion = 'editRegister' : $accion = 'deleteRegister';
              $("#appbundle_novedades_moduloAfectado").css({borderColor:"#b2bec3"});
              $("#observaciones_novedad").css({borderColor:"#b2bec3"});

              if(accion == 'modificar'){
                //loader al modificar datos del módulo.
                vm.formdata[id].progress = true;
              }else if(accion == 'eliminar'){
                //loader al eliminar datos del módulo.
                vm.formdata[id].progressDelete = true;
              }

              if(vm.modulo == 'pagos'){
                if(accion == 'modificar'){
                  if(vm.formdata[id].fecha_consig != null && typeof vm.formdata[id].fecha_consig != 'undefined'){
                    var fecha = moment(vm.formdata[id].fecha_consig).format("YYYY-MM-DD");
                  }

                  if(vm.formdata[id].tiempo_consignacion != null && typeof vm.formdata[id].tiempo_consignacion != 'undefined'){
                    var tiempo = moment(vm.formdata[id].tiempo_consignacion).format("HH:mm:ss");
                  }

                  if(fecha != null && typeof fecha != 'undefined' && tiempo != null && typeof tiempo != 'undefined'){
                    vm.formdata[id].fecha_consignacion = fecha+" "+tiempo;
                  }else{
                    vm.formdata[id].fecha_consignacion = "";
                  }
                }
              }else if(vm.modulo == 'cierres de cajas'){

              }

              var rutaHttp = "";
              var ruta = Api.getAbsolutePath().replace("/novedades","").replace("/new","");
              var rutaHttp = ruta+$accion+"/"+idRegistro+"/"+vm.modulo;
              
              $http.post(rutaHttp,vm.formdata[id]).then(function successCallback(response) {
                if(response.data.status == true){
                  if(accion == 'modificar'){
                    delete vm.formdata[id].progress;
                  }else if(accion == 'modificar'){
                    delete vm.formdata[id].progressDelete;
                  }
                  vm.cerrar(id);//Cierra la ventada de modificar o de eliminar.
                  reinit();//Reinicia los valores del formdata y la data obtenida de los pagos.
                  vm.getInfoPagos();//Consulta nuevamente el pago de la factura.
                  if(accion == 'modificar'){
                    Api.success("Se modificó satisfactoriamente el registro.");
                  }else if(accion == 'eliminar'){
                    Api.success("Se eliminó satisfactoriamente el registro.");
                  }
                }
              })
            }else{
              alert("Es obligatorio que se ingrese las observaciones por la cual se realiza esta novedad.");
              $("#observaciones_novedad").css({borderColor:"#eb2f06"});
            }
          }else{
            alert("Debe seleccionar el módulo ha afectar primero antes de realizar esta acción.");
            $("#appbundle_novedades_moduloAfectado").css({borderColor:"#eb2f06"});
          }
        }

        vm.cerrar = function(id){
            vm.formdata[id].datosModificar = false; //Cierra los datos a modificar de la opción seleccionada.
        }

        vm.cerrarEliminar = function(id){
            vm.formdata[id].eliminar = false; //Cierra los datos a modificar de la opción seleccionada.
        }

    }

})();
