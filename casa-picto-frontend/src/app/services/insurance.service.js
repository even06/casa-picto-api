// src/app/services/insurance.service.js

angular.module('casaPictoApp')
  .service('insuranceService', ['$http', 'apiConfigService', function($http, apiConfigService) {
    var service = this;
    
    // Get list of insurance companies with optional filters
    service.getInsuranceCompanies = function(params) {
      return $http.get(apiConfigService.buildUrl('insurance-companies/list'), { params: params })
        .then(function(response) {
          if (response.data && response.data.success) {
            return response.data.data;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    // Create a new insurance company
    service.createInsuranceCompany = function(data) {
      return $http.post(apiConfigService.buildUrl('insurance-companies/create'), data)
        .then(function(response) {
          if (response.data && response.data.success) {
            return response.data.data;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    // Update an insurance company
    service.updateInsuranceCompany = function(id, data) {
      return $http.put(apiConfigService.buildUrl('insurance-companies/update') + '?id=' + id, data)
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