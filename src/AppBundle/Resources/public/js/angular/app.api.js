(function ()
{
  'use strict';

  angular.module('recaudos')
  .factory("Api",ApiService);

  /* ngInject */
  function ApiService($http){
      var api = {};
      api.idUsuario = null;

      api.alert = function(content){
        $(".ja_wrap .ja_wrap_black").remove();
        $.jAlert({
          'title': 'Sistema de Recaudos Web - Redylab v. 1.0',
          'content': content,
          'theme': 'dark_gray',
          'btns': { text: 'Cerrar' }
        });
      }

      api.warning = function(content){
        $(".ja_wrap .ja_wrap_black").remove();
        $.jAlert({
          title : 'Sistema de Recaudos Web - Redylab v. 1.0',
          content : "<span class='text-warning'>Advertencia:</span> "+content,
          theme : 'dark_gray',
          btns : { text : 'Cerrar'}
        });
      }

      api.success = function(content){
        $(".ja_wrap .ja_wrap_black").remove();
        $.jAlert({
          title : 'Sistema de Recaudos Web - Redylab v. 1.0',
          content : "<span class='text-success'>Proceso Terminado:</span> "+content,
          theme : 'dark_green',
          btns : { text : 'Cerrar'}
        });
      }

      api.error = function(content){
        $(".ja_wrap .ja_wrap_black").remove();
        $.jAlert({
          title : 'Sistema de Recaudos Web - Redylab v. 1.0',
          content : "<span class='text-error'>Error!:</span> "+content,
          theme : 'red',
          btns : { text : 'Cerrar'}
        });
      }

      api.getAbsolutePath = function(){
          var loc = window.location;
          var pathName = loc.pathname.substring(0, loc.pathname.lastIndexOf('/') + 1);
      		var url = loc.href.substring(0, loc.href.length - ((loc.pathname + loc.search + loc.hash).length - pathName.length)).replace("/admin","");

          return url;
      }

      api.fechaActual = function($fecha,$opc){
        if($fecha != null && $fecha != undefined){
          //console.log("aqui no: "+$fecha);
          var fecha = new Date($fecha);
          //console.log("fecha api: "+fecha)
        }else{
          //console.log("aqui");
          var fecha = new Date();
        }

        if($opc != null && $opc != undefined && $opc == 'string'){
            var dd = fecha.getDate();
            var mm = fecha.getMonth()+1;
            var yyyy = fecha.getFullYear();

            var dd = dd < 10 ? '0'+dd : dd;
            var mm = mm < 10 ? '0'+mm : mm;

            return dd+"/"+mm+"/"+yyyy;
        }else{
            return fecha;
        }
      }

      api.setLocalStorage = function(key,value,removeBefore){
          if(removeBefore === true){
             api.removeLocalStorage(key);
          }
          var val = JSON.stringify(value);

          localStorage.setItem(api.idUsuario,val);
      }

      api.getLocalStorage = function(key){
          var value = localStorage.getItem(key);
          return JSON.parse(value);
      }

      api.removeLocalStorage = function(key){
          localStorage.removeItem(key);
      }

      var optionsDatatables = {
          select: true,
          language: {
            "sProcessing":     "Procesando...",
            "sLengthMenu":     "Mostrar _MENU_ registros",
            "sZeroRecords":    "No se encontraron resultados",
            "sEmptyTable":     "Ningún dato disponible en esta tabla",
            "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
            "sInfoPostFix":    "",
            "sSearch":         "Buscar:",
            "sUrl":            "",
            "sInfoThousands":  ",",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
              "sFirst":    "Primero",
              "sLast":     "Último",
              "sNext":     "Siguiente",
              "sPrevious": "Anterior"
            },
            "oAria": {
              "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
              "sSortDescending": ": Activar para ordenar la columna de manera descendente"
            }
          }
      };

      return api;
  }

})();
