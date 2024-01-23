(function ()
{
    'use strict';

    var appPag = angular.module('app.pagination', [])
  	.controller('PaginationCtrl',PaginationCtrl)
  	.filter('startFromGrid', function() {
  	    return function(input, start) {
  	        start =+ start;
  	        return input.slice(start);
  	    }
  	});

	/** @ngInject */
	function PaginationCtrl($scope) {
      var paginate = this;
      paginate.currentPage = 0;
      paginate.pageSize = 4;
      paginate.pages = [];
      paginate.lastRecord = null;
      
      paginate.configPages = function(data) {
        paginate.pages.length = 0;
        var ini = paginate.currentPage - 4;
        var fin = paginate.currentPage + 4;
        if (ini < 1) {
            ini = 1;
            if (Math.ceil(data.length / paginate.pageSize) > 10)
                fin = 10;
            else
                fin = Math.ceil(data.length / paginate.pageSize);
        }else {
            if (ini >= Math.ceil(data.length / paginate.pageSize) - 10) {
                ini = Math.ceil(data.length / paginate.pageSize) - 10;
                fin = Math.ceil(data.length / paginate.pageSize);
            }
        }
        if (ini < 1) ini = 1;
        for (var i = ini; i <= fin; i++) {
            paginate.pages.push({no: i});
        }

        if (paginate.currentPage >= paginate.pages.length){
            paginate.currentPage = paginate.pages.length - 1;
        }
    }

    paginate.setPage = function(index) {
        paginate.currentPage = index - 1;
    };
	}

})();
