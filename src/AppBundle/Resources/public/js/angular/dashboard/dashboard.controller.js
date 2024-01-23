(function ()
{
    'use strict';

    angular.module('app.dashboard',[]).controller('DashboardController',DashboardController);

    /** @ngInject */
    function DashboardController($scope,$http,$filter, Api)
    {
        var vm = this;
        var graphChartjs = {};

        //Filtros de Angular.
        var $currencyFilter = $filter('currency');
        var $limitTo = $filter('limitTo');

        //variables del controller
        vm.limitFive = [];//Los primeros 5 registros se grafican.
        vm.selectEmpresas = null;
        vm.recaudoTotalGeneral = 0;
        vm.sessionesActivasCajeros = 0;
        vm.porcentajeCartera = 0;
        vm.porcentajeRecaudo = 0;
        vm.empresas = [];
        vm.noPagos = false;
        vm.periodos = [];
        vm.mesFact = null;
        vm.anioFact = null;
        vm.filtros = true;
        vm.idle = true;

        vm.connection = true;

        function Init(){
            console.log(":: Init DashboardController JavaScript ::");

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

        vm.showFilters = function(){
          vm.filtros = false;
        }

        vm.changePeriod = function(){
          var ruta = Api.getAbsolutePath().split("app");

          $("#noPagos").empty();
          vm.noPagos = false;
          vm.recaudoTotalGeneral = 0; //El valor del recaudo sin los ajuste de cartera.
          vm.sessionesActivasCajeros = 0;
          vm.porcentajeCartera = 0;
          vm.porcentajeRecaudo = 0;
          $("#loaderCodigo").html('<img id="imgloader" alt="Cargando Visualización de Datos" src="'+ruta[0]+'bundles/app/img/EclipseLoader.gif"><span class="help-block d-block text-center">Cargando Visualización de Datos</span>');

          //Carga de nuevo los datos de la bd y los grafica de nuevo dependiendo de la selección.
          vm.getPagos();

          //Oculta los filtros.
          vm.filtros = true;
        }

        vm.changeSelect = function(){
          //Carga de nuevo los datos de la bd y los grafica de nuevo dependiendo de la selección.
          vm.getPagos();
        }

        //Obtiene los datos a graficar de la base de datos-Recaudos recibidos por cada sede de agencia.
        vm.getPagos = function(){
          vm.getPeriodos();//Carga los select de filtros con los periodos de facturación.
          console.log(":: Get Data ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          if(vm.mesFact != null && vm.anioFact != null){
            rutaHttp = ruta[0]+'getPagos?cartera=true&mes='+vm.mesFact+'&anio='+vm.anioFact+"&isAjax=true";
          }else{
            rutaHttp = ruta[0]+'getPagos?cartera=true&isAjax=true';
          }
          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            if(response.data.status == 1){
                detroyGraph();//Destruye las gráficas ya creadas para que se generen las nuevas.
                vm.noPagos = true;
                serializeDataGraph(response.data);
                $("#loaderCodigo").empty();
                console.log(":: Data Visualization ::");
                if(vm.idle == true){
                  $(document).idle({
                    onIdle: function(){
                      vm.getPagos();
                    },
                    idle: 30000 //30 segundos de Inactividad.
                  });

                  vm.idle = false;
                }
            }else{
              vm.noPagos = false;
              $('#noPagos').html('<span class="alert alert-warning">'+response.data.error+'</span>');
              $("#loaderCodigo").empty();
              vm.noPagos = false;
              vm.recaudoTotalGeneral = 0;
              vm.sessionesActivasCajeros = 0;
              vm.porcentajeCartera = 0;
              vm.porcentajeRecaudo = 0;
            }
          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        //Obtiene los datos de los periodos de facturación.
        vm.getPeriodos = function(){
          console.log(":: Get Periods ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          rutaHttp = ruta[0]+'getPeriodos';

          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            vm.periodos = [];

            $.each(response.data,function(i,value){
              if(i > 0){
                var hasAnio = -1;
                $.each(vm.periodos,function(id,val){
                  if(val.hasOwnProperty("anio") === true){
                    var val = val.anio.indexOf(value.anio_facturado);
                    if(val != -1){
                      hasAnio = val;
                    }
                  }
                });

                if(hasAnio != -1){
                  vm.periodos[i] = {mes:{value:value.mes_facturado,text:value.text_mes_facturado}};
                }else{
                  vm.periodos[i] = {mes:{value:value.mes_facturado,text:value.text_mes_facturado},anio:value.anio_facturado};
                }
              }else{
                vm.periodos[i] = {mes:{value:value.mes_facturado,text:value.text_mes_facturado},anio:value.anio_facturado};
              }
            });
          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        var detroyGraph = function(){
          if(!$.isEmptyObject(graphChartjs)){
            var keys = Object.keys(graphChartjs);
            $.each(keys,function(k,val){
              graphChartjs[val].destroy();
            });
          }
        }

        var serializeDataGraph = function($data){
          var keysEmpresas = Object.keys($data.pagosEmpresas);
          var dataSerialized = {};
          vm.empresas = [];
          var i = 0;

          $.each(keysEmpresas,function(id,value){//Recorro las empresas que esten en el sistema para el usuario actual.
            if(i == 0){
              //Agrega las empresas registradas en el sistema, para cargar el select en la vista.
              vm.empresas.push({
                razonSocial : value,
                selected : true
              });
            }else{
              //Agrega las empresas registradas en el sistema, para cargar el select en la vista.
              vm.empresas.push({
                razonSocial : value
              });
            }

            var keysAgencias = Object.keys($data.pagosEmpresas[value].pagosxAgencias);
            dataSerialized[value] = [];

            $.each(keysAgencias,function(k,v){//Recorre las agencias de cada empresa.
              dataSerialized[value][k] = {};
              dataSerialized[value][k][v] = filterGetTotalPagos($data.pagosEmpresas[value].pagosxAgencias[v]);
            });
          });

          if (vm.selectEmpresas == null){
            vm.selectEmpresas = vm.empresas[0].razonSocial;
          }

          //Selecciona los usuarios activos de la empresa seleccionada.
          vm.sessionesActivasCajeros = $data.pagosEmpresas[vm.selectEmpresas].UsuariosActivos;

          //Muestra el totalRecaudo de la empresa seleccionada.
          vm.recaudoTotalGeneral = $data.pagosEmpresas[vm.selectEmpresas].PagosGeneral;

          //Calculos de porcentaje de cartera y recaudo de la empresa seleccionada.
          vm.porcentajeCartera = ($data.pagosEmpresas[vm.selectEmpresas].totalCartera/$data.pagosEmpresas[vm.selectEmpresas].totalFacturado)*100;
          vm.porcentajeRecaudo = ($data.pagosEmpresas[vm.selectEmpresas].totalRecaudoGeneralCartera/$data.pagosEmpresas[vm.selectEmpresas].totalFacturado)*100;

          //Organizar el objeto de parametros para graficar.
          var optChartLine = getDataGraph('line',dataSerialized);
          var optChartPie = getDataGraph('pie',dataSerialized);

          //Envia el objeto de parametros y grafica.
          vm.graphChart('line',optChartLine);
          vm.graphChart('pie',optChartPie);

        }

        //Filtra todos los pagos recibidos en este periodo y los limita según
        //el límite establecido.
        var filterGetTotalPagos = function(data) {
            if(typeof data != 'object'){
              console.error("El parametro data debe ser un 'object' y se recibío un "+typeof data);
            }

            var salida = [];
            var totalFacturas = 0;
            var fechaUltPago = 0;

            salida.push({
              'totalFacturas': data.totalFacturasRecaudadas,
              'totalRecaudo': data.totalRecaudo
            });

            return salida;
        }

        //Generar la estructura del json del chartjs para crear la gráfica.
        var getDataGraph = function(type,datagraph){
          //Inicializacion Variables.
          var graphData = [];

          $.each(datagraph,function(id,valor){//Recorre el arreglo con cada empresa.
            graphData[id] = {
              container : type,
              type : type,
              data : {},
              options : {}
            }

            //Inicialización.
            graphData[id].data.labels = [];
            graphData[id].data.datasets = [];

            $.each(valor,function(k,vlr){//Recorre cada empresa en sus agencias.
              $.each(vlr,function(key,value){//Agencias
                if(type == 'line'){
                  graphData[id].options.responsive = true
                  graphData[id].options.scales = {
                      yAxes: [{
                          ticks: {
                              beginAtZero:true
                          }
                      }],
                      xAxis:[{
                        lineWidth: 3
                      }]
                  };

                  graphData[id].options.tooltips = {
                      callbacks: {
                        label: function(tooltipItem, data) {
                            var datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                            return datasetLabel + ' : $' + formatNumber(tooltipItem.yLabel, 2, ',', '.');
                          }
                      }
                  }

                  if(k == 0){//Solo se Inicializa en el primer registro de la agencia.
                    graphData[id].data.datasets.push({
                      label : 'Total Recaudado',//Nombre de la Agencia de la cual pertenece los datos.
                      data : [],
                      borderColor: "#D1FB03",//["rgba(236, 125, 26,1)"],
                      backgroundColor: ["rgba(200, 248, 5,0.5)"],//["rgba(236, 125, 26,0.4)"],
                      borderWidth: 2
                    });
                  }

                  graphData[id].options.legend = {
                    display: true,
                    position: 'bottom',
                    labels: {
                      boxWidth: 40,
                      fontColor: '#333'
                    }
                  }
                }else if(type == 'pie'){
                  if(k == 0){//Solo se Inicializa en el primer registro de la agencia.
                    graphData[id].data.datasets[0] = {
                      label : 'Total Facturas Recaudadas',
                      data : [],
                      //borderColor: ["rgba(236, 125, 26,1)"],
                      backgroundColor: [
                          'rgba(255, 99, 132,0.9)',
                          'rgba(54, 162, 235,0.9)',
                          'rgba(255, 206, 86,0.9)',
                          'rgba(75, 192, 192,0.9)',
                          'rgba(153, 102, 255,0.9)',
                          'rgba(255, 159, 164,0.9)',
                          'rgba(255, 192, 186,0.9)',
                          'rgba(250, 112, 186,0.9)',
                          'rgba(245, 122, 156,0.9)'
                      ],
                      borderWidth: 2
                    };
                  }

                  graphData[id].options.responsive = true
                  graphData[id].options.legend = {
                      display: true,
                      position: 'bottom',
                      labels: {
                        boxWidth: 40,
                        fontColor: '#333'
                      }
                  }
                }

                $.each(value,function(ky,val){//Recorre cada dato de cada pago recibido por cada agencia.
                  if(type == 'line'){
                    graphData[id].data.labels.push(key);
                    graphData[id].data.datasets[ky].data.push(val.totalRecaudo);
                  }else if(type == 'pie'){
                    graphData[id].data.labels.push(key);//Nombres de las Agencias.
                    graphData[id].data.datasets[ky].data.push(val.totalFacturas);
                  }
                });

              });
            });
          });

          return graphData;
        }

        //Envia a crear la grafica de acuerdo al select #empresas.
        vm.graphChart = function(type,optChart){
          if(type == 'line'){
            //total valor recaudados.
            var ctxLine = $("#"+optChart[vm.selectEmpresas].container);
            var myLineChart = new Chart(ctxLine, optChart[vm.selectEmpresas]);
            graphChartjs.myLineChart = myLineChart;
          }else if(type == 'pie'){
            //total facturas recaudadas.
            var ctxPie = $("#"+optChart[vm.selectEmpresas].container);
            var myPieChart = new Chart(ctxPie, optChart[vm.selectEmpresas]);
            graphChartjs.myPieChart = myPieChart;
          }

          return graphChartjs;
        }

        var formatNumber = function(number, decimalsLength, decimalSeparator, thousandSeparator) {
             var n = number,
              decimalsLength = isNaN(decimalsLength = Math.abs(decimalsLength)) ? 2 : decimalsLength,
              decimalSeparator = decimalSeparator == undefined ? "," : decimalSeparator,
              thousandSeparator = thousandSeparator == undefined ? "." : thousandSeparator,
              sign = n < 0 ? "-" : "",
              i = parseInt(n = Math.abs(+n || 0).toFixed(decimalsLength)) + "",
              j = (j = i.length) > 3 ? j % 3 : 0;

             return sign +
              (j ? i.substr(0, j) + thousandSeparator : "") +
              i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + thousandSeparator) +
              (decimalsLength ? decimalSeparator + Math.abs(n - i).toFixed(decimalsLength).slice(2) : "");
        }

    }

})();
