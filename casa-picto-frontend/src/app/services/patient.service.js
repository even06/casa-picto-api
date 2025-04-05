// src/app/services/patient.service.js

angular.module('casaPictoApp')
  .service('patientService', ['$http', 'apiConfigService', function($http, apiConfigService) {
    var service = this;
    
    // Get list of patients with optional filters
    service.getPatients = function(params) {
      return $http.get(apiConfigService.buildUrl('patients/list'), { params: params })
        .then(function(response) {
          if (response.data && response.data.success) {
            return response.data.data;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    // Get a single patient by ID
    service.getPatient = function(id) {
      return $http.get(apiConfigService.buildUrl('patients/get'), { params: { id: id } })
        .then(function(response) {
          if (response.data && response.data.success) {
            return response.data.data;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    // Get payment history for a patient
    service.getPaymentHistory = function(id, params) {
      return $http.get(apiConfigService.buildUrl('patients/payment-history'), 
        { params: Object.assign({id: id}, params || {}) })
        .then(function(response) {
          if (response.data && response.data.success) {
            return response.data.data;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    // Get attendance history for a patient
    service.getAttendanceHistory = function(id, params) {
      return $http.get(apiConfigService.buildUrl('patients/attendance-history'), 
        { params: Object.assign({id: id}, params || {}) })
        .then(function(response) {
          if (response.data && response.data.success) {
            return response.data.data;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    // Get all unique CUD types from patients
    service.getCudTypes = function() {
      // This could be replaced with a dedicated API endpoint in the future
      return service.getPatients({ limit: 1000 })
        .then(function(data) {
          var patients = data.patients || [];
          var types = new Set();
          
          patients.forEach(function(patient) {
            if (patient.has_cud && patient.cud_type && patient.cud_type.trim()) {
              types.add(patient.cud_type.trim());
            }
          });
          
          return Array.from(types).sort();
        });
    };
    
    return service;
  }]);