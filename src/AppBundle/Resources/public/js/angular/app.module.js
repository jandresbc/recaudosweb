(function ()
{
  'use strict';

    /**
     * Main module of the recaudos
     */
    var apprecaudos = angular.module('recaudos', ['app.pagination','app.transacciones','app.dashboard','app.novedades','app.business'],function($interpolateProvider) { 
      $interpolateProvider.startSymbol("{[{");
      return $interpolateProvider.endSymbol("}]}");
    });


})()
