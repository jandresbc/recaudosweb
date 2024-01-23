(function ()
{
    'use strict';

    angular.module('app.business',[]).controller('BusinessController',BusinessController);

    /** @ngInject */
    function BusinessController($scope,$http,$filter, Api)
    {
        var vm = this;
        var graphChartjs = {};

        //Filtros de Angular.
        var $currencyFilter = $filter('currency');
        var $limitTo = $filter('limitTo');

        //variables del controller
        vm.datosGenerados = "Datos Generados el "+moment().format("DD/MM/YYYY hh:mm:ss a");
        vm.threads = {};
        vm.selectEmpresas = null;
        vm.empresas = [];
        vm.periodos = [];
        vm.mesSelectedFact = null;
        vm.anioSelectedFact = null;
        vm.idle = true;

        vm.connection = true;

        //Initial
        vm.Init = function(){
            vm.getPeriodos(function(){
              vm.getAllData();//Trae todos los datos Iniciales.
            });
            console.log(":: Init businessController JavaScript ::");

            window.addEventListener('offline', function(){
                Api.warning("Se detectó que NO hay conexión a internet.");
                vm.connection = false;
            });

            window.addEventListener('online', function(){
                Api.alert("Ya cuenta con conexión a internet.");
                vm.connection = true;
            });
        }

        //Trae del back-end todos los datos para generar la visualización de los datos.
        vm.getAllData = function(){
          vm.getPagosPorDia();//Trae los pagos para la primera gráfica.
          vm.getPagosAnual();//Trae los pagos para la segunda gráfica.
          vm.getPagosMunicipio();//Trae los pagos para la tercera gráfica.
          vm.getNovedadesAnio();//Trae las novedades por año para la cuarta gráfica.
          vm.getNovedadesMes();//Trae las novedades por mes de acuerdo al año seleccionado para la quinta gráfica.
          vm.getCrecimientoRecaudo();//Trae el porcentaje de crecimiento del recaudo del año seleccionado para la sexta gráfica.
          vm.getPagosCartera();//Trae los dtos del recaudo del año seleccionado para la septima gráfica.
        }

        vm.refreshData = function(){
          //Destuye todas las gráficas para generar las nuevas.
          detroyAllGraph();
          vm.Init();//Trae de vuelta todos los datos del back-end.
        }

        vm.changeSelectEmpresa = function(){
          //Destruye todas las gráficas para generar las nuevas.
          detroyAllGraph();
          //Carga de nuevo los datos de la bd y los grafica de nuevo dependiendo de la selección.
          vm.Init();
        }

        //Obtiene los datos a graficar de la base de datos-Recaudos recibidos por dia recaudado.
        vm.getPagosPorDia = function(){
          console.log(":: Get Data Pagos por Día ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          if(vm.mesSelectedFact != null && vm.anioSelectedFact != null){
            rutaHttp = ruta[0]+'apiGetPagos/'+vm.mesSelectedFact+'/'+vm.anioSelectedFact+'?empresa='+vm.selectEmpresas;
          }else{
            rutaHttp = ruta[0]+'apiGetPagos/null/null?empresa='+vm.selectEmpresas;
          }
          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            var fechas = [];
            var valores = [];

            $.each(response.data,function(index,value){
              fechas.push(value.fechaPago)
              valores.push(value.totalRecaudoDia)
            })

            //agrega los datos a graficar al threads.
            vm.threads[vm.selectEmpresas]["Grafica1"] = {
              data : {
                labels : fechas,
                datasets : [{
                  data : valores
                }]
              }
            }

            var ctxGrafica1 = document.getElementById("grafica1").getContext('2d');

            var opciones = {
              type : 'line',
              data : {
                labels: fechas,
                datasets: [{
                    label: 'Recaudo total por día.',
                    data: valores,
                    backgroundColor: [
                      'rgba(39, 174, 96, 0.7)'
                    ],
                    borderColor: [
                      'rgba(39, 174, 96, 1)'
                    ],
                    borderWidth: 2
                }]
              },
              options : {
                responsive : true,
                legend : {
                  position : 'bottom'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero:true
                        }
                    }],
                    xAxis:[{
                      lineWidth: 3
                    }]
                },
                title : {
                  display : false,
                  text : "Gráfica: Recaudo por día."
                },
                tooltips: {
                    callbacks: {
                      label: function(tooltipItem, data) {
                        var datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ' : $' + formatNumber(tooltipItem.yLabel, 2, ',', '.');
                      }
                    }
                }
              }
            }

            var chartGrafica1 = new Chart(ctxGrafica1, opciones);
            graphChartjs.chartGrafica1 = chartGrafica1;

          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        //Obtiene los datos a graficar de la base de datos-Recaudos recibidos por cada año.
        vm.getPagosAnual = function(){
          console.log(":: Get Data Pagos Anuales ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          rutaHttp = ruta[0]+'apiGetPagos/null/null?hasTotalAño=true&empresa='+vm.selectEmpresas;

          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            var anios = [];
            var valores = [];

            $.each(response.data,function(index,value){
              anios.push(value.anioFacturado)
              valores.push(value.totalRecaudado)
            })

            var ctxGrafica2 = document.getElementById("grafica2").getContext('2d');

            var opciones = {
              type : 'bar',
              data : {
                labels: anios,
                datasets: [{
                    label: 'Recaudo total por Año.',
                    data: valores,
                    backgroundColor: [
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                    borderColor: [
                      'rgba(153, 102, 255, 1)',
                      'rgba(54, 162, 235, 1)',
                      'rgba(255, 206, 86, 1)',
                      'rgba(75, 192, 192, 1)',
                      'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 2
                }]
              },
              options : {
                responsive : true,
                legend : {
                  position : 'bottom'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero:true
                        }
                    }],
                    xAxis:[{
                      lineWidth: 3
                    }]
                },
                tooltips: {
                    callbacks: {
                      label: function(tooltipItem, data) {
                          var datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                          return datasetLabel + ' : $' + formatNumber(tooltipItem.yLabel, 2, ',', '.');
                        }
                    }
                }
              }
            }

            var chartGrafica2 = new Chart(ctxGrafica2, opciones);
            graphChartjs.chartGrafica2 = chartGrafica2;

          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        //Obtiene los pagos por municipio.
        vm.getPagosMunicipio = function(){
          console.log(":: Get Data Pagos por Municipio ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          if(vm.mesSelectedFact != null && vm.anioSelectedFact != null){
            rutaHttp = ruta[0]+'apiGetPagos/'+vm.mesSelectedFact+'/'+vm.anioSelectedFact+"?hasPagosMunicipio=true&empresa="+vm.selectEmpresas;
          }else{
            rutaHttp = ruta[0]+'apiGetPagos/6/2018?hasPagosMunicipio=true&empresa='+vm.selectEmpresas;
          }
          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            var municipios = [];
            var valores = [];

            $.each(response.data,function(index,value){
              municipios.push(value.municipio)
              valores.push(value.totalRecaudado)
            })

            var ctxGrafica3 = document.getElementById("grafica3").getContext('2d');

            var opciones = {
              type : 'horizontalBar',
              data : {
                labels: municipios,
                datasets: [{
                    label: 'Recaudo total por Municipio.',
                    data: valores,
                    backgroundColor: [
                      'rgba(255, 206, 86, 0.8)',
                      'rgba(241, 196, 15, 0.8)',
                      'rgba(255, 99, 132, 0.8)',
                      'rgba(54, 162, 235, 0.8)',
                      'rgba(75, 192, 192, 0.8)',
                      'rgba(153, 102, 255, 0.8)',
                      'rgba(255, 159, 64, 0.5)',
                      'rgba(51, 168, 255, 0.5)',
                      'rgba(2, 229, 247, 0.5)',
                      'rgba(255, 84, 84, 0.5)',
                      'rgba(255, 133, 84, 0.5)',
                      'rgba(247, 251, 3, 0.5)',
                      'rgba(188, 123, 2, 0.5)'
                    ],
                    borderColor: [
                      'rgba(255, 206, 86, 1)',
                      'rgba(241, 196, 15, 1)',
                      'rgba(255,99,132, 1)',
                      'rgba(54, 162, 235, 1)',
                      'rgba(75, 192, 192, 1)',
                      'rgba(153, 102, 255, 1)',
                      'rgba(255, 159, 64, 1)',
                      'rgba(51, 168, 255, 1)',
                      'rgba(2, 229, 247, 1)',
                      'rgba(255, 84, 84, 1)',
                      'rgba(255, 133, 84, 1)',
                      'rgba(247, 251, 3, 1)',
                      'rgba(188, 123, 2, 1)'
                    ],
                    borderWidth: 2
                }]
              },
              options : {
                responsive : true,
                legend : {
                  position : 'bottom'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero:true
                        }
                    }],
                    xAxis:[{
                      lineWidth: 2
                    }]
                },
                tooltips: {
                    callbacks: {
                      label: function(tooltipItem, data) {
                          var datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                          return datasetLabel + ' : $' + formatNumber(tooltipItem.xLabel, 2, ',', '.');
                        }
                    }
                }
              }
            }

            var chartGrafica3 = new Chart(ctxGrafica3, opciones);
            graphChartjs.chartGrafica3 = chartGrafica3;

          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        //Obtiene los novedades por año.
        vm.getNovedadesAnio = function(){
          console.log(":: Get Data Novedades por Año ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          rutaHttp = ruta[0]+'apiNovedadesUsuarios/all?hasNovedadesAño=true&empresa='+vm.selectEmpresas;

          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            var anioNovedades = [];
            var cantidades = [];

            $.each(response.data,function(index,value){
              anioNovedades.push(value.anio)
              cantidades.push(value.totalNovedades)
            })

            var ctxGrafica4 = document.getElementById("grafica4").getContext('2d');

            var opciones = {
              type : 'pie',
              data : {
                labels: anioNovedades,
                datasets: [{
                    label: 'Recaudo Novedades por Año.',
                    data: cantidades,
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.9)',
                        'rgba(255, 99, 132, 0.9)',
                        'rgba(54, 162, 235, 0.9)',
                        'rgba(255, 206, 86, 0.9)',
                        'rgba(153, 102, 255, 0.9)',
                        'rgba(255, 159, 64, 0.9)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255,99,132,1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 2
                }]
              },
              options : {
                responsive : true,
                legend : {
                  position : 'bottom'
                }
              }
            }

            var chartGrafica4 = new Chart(ctxGrafica4, opciones);
            graphChartjs.chartGrafica4 = chartGrafica4;

          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        //Obtiene las novedades mensualmente por cada año seleccionado.
        vm.getNovedadesMes = function(){
          console.log(":: Get Data Novedades por Mes ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          rutaHttp = ruta[0]+'apiNovedadesUsuarios/all?hasNovedadesAño=true&anio='+vm.anioSelectedFact+"&empresa="+vm.selectEmpresas;

          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            var mesNovedades = [];
            var cantidades = [];
            var meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];

            $.each(response.data,function(index,value){
              mesNovedades.push(meses[(value.mes-1)])
              cantidades.push(value.totalNovedades)
            })

            var ctxGrafica5 = document.getElementById("grafica5").getContext('2d');

            var opciones = {
              type : 'doughnut',
              data : {
                labels: mesNovedades,
                datasets: [{
                    label: 'Recaudo Novedades por Mes.',
                    data: cantidades,
                    backgroundColor: [
                      'rgba(255, 206, 86, 0.9)',
                      'rgba(153, 102, 255, 0.9)',
                      'rgba(255, 159, 64, 0.9)',
                      'rgba(75, 192, 192, 0.9)',
                      'rgba(255, 99, 132, 0.9)',
                      'rgba(54, 162, 235, 0.9)',
                      'rgba(138, 43, 226, 0.9)',
                      'rgba(220, 220, 220, 0.9)',
                      'rgba(173, 255, 47, 0.9)',
                      'rgba(0, 255, 255, 0.9)',
                      'rgba(64, 224, 208, 0.9)',
                      'rgba(102, 51, 153, 0.9)'
                    ],
                    borderColor: [
                      'rgba(255, 206, 86, 1)',
                      'rgba(153, 102, 255, 1)',
                      'rgba(255, 159, 64, 1)',
                      'rgba(75, 192, 192, 1)',
                      'rgba(255, 99, 132, 1)',
                      'rgba(54, 162, 235, 1)',
                      'rgba(138, 43, 226, 1)',
                      'rgba(220, 220, 220, 1)',
                      'rgba(173, 255, 47, 1)',
                      'rgba(0, 255, 255, 1)',
                      'rgba(64, 224, 208, 1)',
                      'rgba(102, 51, 153, 1)'
                    ],
                    borderWidth: 2
                }]
              },
              options : {
                responsive : true,
                legend : {
                  position : 'bottom'
                },
                circumference : Math.PI,//Para hacer media luna.
                rotation : -Math.PI//Para hacer media luna.
              }
            }

            var chartGrafica5 = new Chart(ctxGrafica5, opciones);
            graphChartjs.chartGrafica5 = chartGrafica5;

          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        //Obtiene los pagos mensualmente por cada año seleccionado.
        vm.getCrecimientoRecaudo = function(){
          console.log(":: Get Data Novedades de Crecimiento del Recaudo mes a mes ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          rutaHttp = ruta[0]+'apiGetPagos/null/'+vm.anioSelectedFact+'?hasTotalMes=true&empresa='+vm.selectEmpresas;

          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            var mesPagos = [];
            var porcentajes = [];
            var meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
            var mesPeriodoActual = (moment().format("MM"))-1;

            $.each(response.data,function(index,value){
              if(value.mesFacturado != mesPeriodoActual){//Si se quiere que el actual mes de recaudo no aparezca en la gráfica.
                mesPagos.push(meses[(value.mesFacturado-1)])
                porcentajes.push(value.porcentajeCrecimiento)
              }
            })

            var ctxGrafica6 = document.getElementById("grafica6").getContext('2d');

            var opciones = {
              type : 'line',
              data : {
                labels: mesPagos,
                datasets: [{
                    label: '% de Crecimiento del Recaudo',
                    data: porcentajes,
                    backgroundColor : [
                      'rgba(255,99,132,0.5)'
                    ],
                    borderColor: [
                      'rgba(255,99,132,1)'
                    ],
                    showLine : false,
                    // borderWidth: 2,
                    fill : false,
                    pointRadius : 12,
                    pointHoverRadius : 12,
                    // borderDash : [5,5] //Linea punteada
                }]
              },
              options : {
                responsive : true,
                scales: {
                    yAxes: [{
                        ticks: {
                            // Include a dollar sign in the ticks
                            callback: function(value, index, values) {
                                return '%' + value;
                            }
                        }
                    }]
                },
                legend : {
                  position : 'bottom'
                },
                title :{
                  display : false,
                  text : "Porcentaje de Crecimiento del recaudo respecto a cada mes facturado."
                },
                tooltips: {
                    callbacks: {
                      label: function(tooltipItem, data) {
                          var datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                          return datasetLabel + ' : ' + tooltipItem.yLabel + '%';
                        }
                    }
                }
              }
            }

            var chartGrafica6 = new Chart(ctxGrafica6, opciones);
            graphChartjs.chartGrafica6 = chartGrafica6;

          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        //Obtiene los pagos mensualmente por cada año seleccionado.
        vm.getPagosCartera = function(){
          console.log(":: Get Data Novedades de Pagos Cartera ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          rutaHttp = ruta[0]+'apiGetPagosCartera/'+vm.anioSelectedFact+'/'+vm.selectEmpresas;

          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            var datasets = [];
            var mesesData = [];
            var meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
            var mesPeriodoActual = (moment().format("MM"))-1;

            //Datasets Iniciales.
            datasets[0] = {
              label : "Facturado",
              borderWidth : 1,
              data : [],
              backgroundColor : ["#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646"],
              borderColor : ["#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646","#A8E646"]
            };

            datasets[1] = {
              label : "Recaudado",
              borderWidth : 1,
              data : [],
              backgroundColor : ["orange","orange","orange","orange","orange","orange","orange","orange","orange","orange","orange","orange"],
              borderColor : ["orange","orange","orange","orange","orange","orange","orange","orange","orange","orange","orange","orange"]
            };

            datasets[2] = {
              label : "Cartera",
              borderWidth : 1,
              data : [],
              backgroundColor : ["red","red","red","red","red","red","red","red","red","red","red","red"],
              borderColor : ["red","red","red","red","red","red","red","red","red","red","red","red"]
            };

            $.each(response.data,function(index,value){
              mesesData.push(meses[value.mes_facturado-1]);

              datasets[0].data[index] = value.totalFacturado;
              datasets[1].data[index] = value.totalRecaudoGeneral;
              datasets[2].data[index] = value.totalCartera;
            })

            var ctxGrafica7 = document.getElementById("grafica7").getContext('2d');

            var opciones = {
              type : 'bar',
              data : {
                labels: mesesData,
                datasets: datasets
              },
              options : {
                responsive : true,
                scales: {
                    yAxes: [{
                        ticks: {
                            // Include a dollar sign in the ticks
                            callback: function(value, index, values) {
                                return '$' + value;
                            }
                        }
                    }]
                },
                legend : {
                  position : 'bottom'
                },
                title :{
                  display : false,
                  text : "Análisis del recaudo mes a mes."
                },
                tooltips: {
                    callbacks: {
                      label: function(tooltipItem, data) {
                        var datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ' : $' + formatNumber(tooltipItem.yLabel, 2, ',', '.');
                      }
                    }
                }
              }
            }

            var chartGrafica7 = new Chart(ctxGrafica7, opciones);
            graphChartjs.chartGrafica7 = chartGrafica7;

          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        //Obtiene los datos de los periodos de facturación.
        vm.getPeriodos = function(callback){
          console.log(":: Get Periodos ::");
          var ruta = Api.getAbsolutePath().split("transacciones");
          var rutaHttp = "";

          rutaHttp = ruta[0]+'apiGetPeriodos';

          //realiza la petición al servidor.
          $http({
            method: 'GET',
            url: rutaHttp
          }).then(function successCallback(response) {
            vm.periodos = [];
            vm.empresas = Object.keys(response.data);
            vm.selectEmpresas = vm.selectEmpresas == null ? vm.empresas[0] : vm.selectEmpresas;
            vm.mesSelectedFact = (moment().format("MM"))-1;//mes actual se asigna por defecto.
            vm.anioSelectedFact = moment().format("YYYY");//Año actual se asigna por defecto.
            vm.threads = response.data; //Se agrega al hilo la información como llega de la peticion de periodos al sistema. Esta debe ir Init.
            $.each(vm.threads[vm.selectEmpresas].periodosFacturacion,function(i,value){
              if(i > 0){
                var hasAnio = -1;
                var hasMes = -1;
                $.each(vm.periodos[vm.selectEmpresas],function(id,valor){
                  if(valor.hasOwnProperty("anio") === true){
                    var val = valor.anio.indexOf(value.anio_facturado);
                    if(val != -1){
                      hasAnio = val;
                    }
                  }

                  if(valor.hasOwnProperty("mes") === true){
                    var val2 = valor.mes.value.indexOf(value.mes_facturado);
                    if(val2 != -1){
                      hasMes = val2;
                    }
                  }
                });

                if(hasAnio != -1){
                  if(hasMes != -1){
                    vm.periodos[vm.selectEmpresas][i] = {anio:value.anio_facturado};
                  }else{
                    vm.periodos[vm.selectEmpresas][i] = {mes:{value:value.mes_facturado,text:value.text_mes_facturado}};
                  }
                }else{
                  if(hasMes != -1){
                      vm.periodos[vm.selectEmpresas][i] = {anio:value.anio_facturado};
                  }else{
                    vm.periodos[vm.selectEmpresas][i] = {mes:{value:value.mes_facturado,text:value.text_mes_facturado},anio:value.anio_facturado};
                  }
                }
              }else{
                vm.periodos[vm.selectEmpresas] = [];
                vm.periodos[vm.selectEmpresas][i] = {mes:{value:value.mes_facturado,text:value.text_mes_facturado},anio:value.anio_facturado};
              }

              //Se marca el último mes facturado del periodo para que se el mes seleccionado.
              if((vm.threads[vm.selectEmpresas].periodosFacturacion.length-1) == i){
                vm.mesSelectedFact = value.mes_facturado;
              }
            });

            //Ejectua el callback
            if(typeof callback == 'function'){
              callback();
            }
          }, function errorCallback(response) {
            // called asynchronously if an error occurs
            // or server returns response with an error status.
          });
        }

        var detroyAllGraph = function(){
          if(!$.isEmptyObject(graphChartjs)){
            var keys = Object.keys(graphChartjs);
            $.each(keys,function(k,val){
              graphChartjs[val].destroy();
            });
          }
        }

        //Filtra todos los pagos recibidos en este periodo y los limita según
        //el límite establecido.
        vm.filter = function(idGrafica,funcionDatos) {
            if(vm.mesSelectedFact == "" || vm.mesSelectedFact == null || typeof vm.mesSelectedFact == undefined){
              alert("Para realizar esta acción debe tener seleccionado el mes facturado.");
              return false;
            }

            if(vm.anioSelectedFact == "" || vm.anioSelectedFact == null || typeof vm.anioSelectedFact == undefined){
              alert("Para realizar esta acción debe tener seleccionado el año facturado.");
              return false;
            }
            var chart = "chart"+idGrafica;
            graphChartjs[chart].clear();
            graphChartjs[chart].destroy();
            //Ejecuta la función que trae los datos del back-end segun los filtros seleccionados.
            funcionDatos();
        }

        //Permite descargar la imagen de la gráfica generada.
        vm.download = function(idGrafica){
          var chart = "chart"+idGrafica;
          // graphChartjs[chart].options.title.display = true;
          if(typeof graphChartjs[chart] == 'object'){
            graphChartjs[chart].update();

            var chart = "chart"+idGrafica;
            var base64 = graphChartjs[chart].toBase64Image();

            setTimeout("download('"+base64+"', '"+idGrafica+".png', 'image/png')",500);
          }else{
            console.log("El objeto chart no existe o no se ha creado aún.");
          }
        }

        //Le da formato a los valore de moneda en las gráfica para que sean mejor leidos
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
