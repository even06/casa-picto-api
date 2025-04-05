// src/app/services/professional-availability.service.js

angular.module('casaPictoApp')
  .service('professionalAvailabilityService', ['$http', 'apiConfigService', function($http, apiConfigService) {
    var service = this;

    // Get availability for a professional
    service.getAvailability = function(professionalId) {
      return $http.get(apiConfigService.buildUrl('professionals/get'), {
        params: { id: professionalId }
      }).then(function(response) {
        if (response.data && response.data.success) {
          return response.data.data.availability || [];
        } else {
          throw new Error('Failed to get professional availability');
        }
      });
    };
    
    // Update availability for a specific day of the week
    service.updateAvailability = function(professionalId, availabilityData) {
      return $http.post(
        apiConfigService.buildUrl('professionals/availability/update') + '?id=' + professionalId,
        availabilityData
      ).then(function(response) {
        if (response.data && response.data.success) {
          return response.data.data;
        } else {
          throw new Error('Failed to update professional availability');
        }
      });
    };
    
    // Add availability exception (holiday, time off, etc.)
    service.addException = function(professionalId, exceptionData) {
      return $http.post(
        apiConfigService.buildUrl('professionals/availability/exception') + '?id=' + professionalId,
        exceptionData
      ).then(function(response) {
        if (response.data && response.data.success) {
          return response.data.data;
        } else {
          throw new Error('Failed to add availability exception');
        }
      });
    };
    
    // Helper functions
    service.getDaysOfWeek = function() {
      return [
        { value: 'MONDAY', label: 'Monday' },
        { value: 'TUESDAY', label: 'Tuesday' },
        { value: 'WEDNESDAY', label: 'Wednesday' },
        { value: 'THURSDAY', label: 'Thursday' },
        { value: 'FRIDAY', label: 'Friday' },
        { value: 'SATURDAY', label: 'Saturday' },
        { value: 'SUNDAY', label: 'Sunday' }
      ];
    };
    
    // Generate timeSlots in 30-min increments from 08:00 to 19:00
    service.getTimeSlots = function() {
      var slots = [];
      var current = new Date();
      current.setHours(8, 0, 0, 0);
      
      var end = new Date();
      end.setHours(19, 0, 0, 0);
      
      while (current <= end) {
        var hours = current.getHours().toString().padStart(2, '0');
        var minutes = current.getMinutes().toString().padStart(2, '0');
        slots.push(hours + ':' + minutes + ':00');
        
        // Add 30 minutes
        current.setMinutes(current.getMinutes() + 30);
      }
      
      return slots;
    };
    
    return service;
  }]);