angular.module('casaPictoApp')
  .service('professionalService', ['$http', 'apiConfigService', function($http, apiConfigService) {
    var service = this;
    
    // Get list of professionals with optional filters
    service.getProfessionals = function(params) {
      return $http.get(apiConfigService.buildUrl('professionals/list'), { params: params })
        .then(function(response) {
          if (response.data && response.data.success) {
            return response.data.data;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    // Get a single professional by ID
    service.getProfessional = function(id) {
      return $http.get(apiConfigService.buildUrl('professionals/get'), { params: { id: id } })
        .then(function(response) {
          if (response.data && response.data.success) {
            return response.data.data;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    return service;
  }]);