  //Copyright 2018 www.devstudio.me
  //app contiene todas las funciones javascript que necesita
  //la app para funcionar.
  var app = {};
  //Canvas Chartjs
  //Forza la descarga de la imagen de un elemento canvas.
  app.downloadChartCanvas = function(idCanvas){
    canvas = document.getElementById(idCanvas);
    var chart = canvas.toDataURL("image/png");
    chart = chart.replace("image/png", "image/octet-stream");
    document.location.href = chart;
  }

  //Crea una imagen a partir del elemento canva del charjs a un elemento img
  app.createImageCanvas = function(idCanvas,idImage){
    var url = document.getElementById(idCanvas).toDataURL("image/png");
    document.getElementById(idImage).src = url;
  }

  app.getAbsolutePath = function(){
      var loc = window.location;
      var pathName = loc.pathname.substring(0, loc.pathname.lastIndexOf('/') + 1);
      var url = loc.href.substring(0, loc.href.length - ((loc.pathname + loc.search + loc.hash).length - pathName.length)).replace("/admin","");

      return url;
  }

  app.getStatusRecaudo = function(repl){
    var URL = this.getAbsolutePath().replace(repl,"");

    $.ajax({
      url : URL+"getStatusRecaudo",
      method : 'GET',
      success : function(response){
        console.log(":: Get Status Recaudo ::");
        var html = "";

        $("#estadoRecaudo").empty()

        $.each(response,function(i,res){
          if(res.status == 'Recaudo Finalizado'){
            $("#estadoRecaudo").fadeOut().fadeIn(2000);
            var fechaVenc = moment(res.fechaVencimiento).format("DD/MM/YYYY hh:mm:ss A");
            if(i == 0){
              $("#estadoRecaudo").append(html+"<span class='text-left'><b><i>"+res.razonSocial+"</i></b><br> Periodo "+res.mesFacturado+" / "+res.anioFacturado+": <b><u>"+res.status+"</u></b><br> Última Fecha Vencimiento: "+fechaVenc+"</span>");
            }else{
              $("#estadoRecaudo").append("<span class='text-left'><b><i>"+res.razonSocial+"</i></b><br> Periodo "+res.mesFacturado+" / "+res.anioFacturado+": <b><u>"+res.status+"</u></b><br> Última Fecha Vencimiento: "+fechaVenc+"</span>");
            }
          }else if(res.status == 'Recaudo Activo'){
            $("#estadoRecaudo").hide();
            // $("#estadoRecaudo").fadeOut().fadeIn(2000);
            // if(i == 0){
            //   $("#estadoRecaudo").append(html+"<span class='text-left'><b><i>"+res.razonSocial+"</i></b><br> Periodo "+res.mesFacturado+" / "+res.anioFacturado+": <b><u>"+res.status+"</u></b></span>");
            // }else{
            //   $("#estadoRecaudo").append("<span class='text-left'><b><i>"+res.razonSocial+"</i></b><br> Periodo "+res.mesFacturado+" / "+res.anioFacturado+": <b><u>"+res.status+"</u></b></span>");
            // }
          }
        })
      }
    })
  }
