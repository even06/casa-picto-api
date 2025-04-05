// src/app/components/professionals/professional-availability.component.js
import templateHtml from './professional-availability.template.html?raw';

angular.module('casaPictoApp')
  .component('professionalAvailability', {
    template: templateHtml,
    bindings: {
      professionalId: '<',
      onUpdate: '&'
    },
    controller: ['$scope', 'professionalAvailabilityService', ProfessionalAvailabilityController]
  });

function ProfessionalAvailabilityController($scope, professionalAvailabilityService) {
  var ctrl = this;
  
  // Data
  ctrl.daysOfWeek = professionalAvailabilityService.getDaysOfWeek();
  ctrl.timeSlots = professionalAvailabilityService.getTimeSlots();
  ctrl.availability = [];
  ctrl.selectedDay = null;
  ctrl.currentAvailability = null;
  
  // UI states
  ctrl.isLoading = false;
  ctrl.isSubmitting = false;
  ctrl.error = null;
  ctrl.success = null;
  
  // Initialize component
  ctrl.$onInit = function() {
    ctrl.loadAvailability();
  };
  
  // Load availability for the professional
  ctrl.loadAvailability = function() {
    if (!ctrl.professionalId) {
      ctrl.error = 'No professional selected';
      return;
    }
    
    ctrl.isLoading = true;
    ctrl.error = null;
    
    professionalAvailabilityService.getAvailability(ctrl.professionalId)
      .then(function(data) {
        ctrl.availability = data;
        // Create empty slots for days with no availability set
        ctrl.ensureAllDaysExist();
      })
      .catch(function(error) {
        console.error('Error loading availability', error);
        ctrl.error = 'Failed to load availability data. Please try again.';
      })
      .finally(function() {
        ctrl.isLoading = false;
      });
  };
  
  // Ensure all days have at least an empty availability entry
  ctrl.ensureAllDaysExist = function() {
    // Create a map of existing availability by day
    var availabilityByDay = {};
    ctrl.availability.forEach(function(item) {
      availabilityByDay[item.day_of_week] = item;
    });
    
    // Add empty slots for missing days
    ctrl.daysOfWeek.forEach(function(day) {
      if (!availabilityByDay[day.value]) {
        ctrl.availability.push({
          day_of_week: day.value,
          start_time: null,
          end_time: null,
          is_active: true,
          isNew: true // Flag to indicate this is a new record
        });
      }
    });
    
    // Sort by day of week
    ctrl.sortAvailability();
  };
  
  // Sort availability by day of week
  ctrl.sortAvailability = function() {
    var dayOrder = {
      'MONDAY': 1,
      'TUESDAY': 2,
      'WEDNESDAY': 3,
      'THURSDAY': 4,
      'FRIDAY': 5,
      'SATURDAY': 6,
      'SUNDAY': 7
    };
    
    ctrl.availability.sort(function(a, b) {
      return dayOrder[a.day_of_week] - dayOrder[b.day_of_week];
    });
  };
  
  // Get display name for a day of week
  ctrl.getDayDisplayName = function(dayCode) {
    var day = ctrl.daysOfWeek.find(function(d) {
      return d.value === dayCode;
    });
    return day ? day.label : dayCode;
  };
  
  // Select a day to edit
  ctrl.selectDay = function(day) {
    ctrl.selectedDay = day;
    ctrl.currentAvailability = angular.copy(day);
    ctrl.error = null;
    ctrl.success = null;
  };
  
  // Save availability for the selected day
  ctrl.saveAvailability = function() {
    if (!ctrl.currentAvailability.start_time || !ctrl.currentAvailability.end_time) {
      ctrl.error = 'Please select both start and end times';
      return;
    }
    
    if (ctrl.currentAvailability.start_time >= ctrl.currentAvailability.end_time) {
      ctrl.error = 'End time must be after start time';
      return;
    }
    
    ctrl.isSubmitting = true;
    ctrl.error = null;
    ctrl.success = null;
    
    // Prepare data for API
    var data = {
      day_of_week: ctrl.currentAvailability.day_of_week,
      start_time: ctrl.currentAvailability.start_time,
      end_time: ctrl.currentAvailability.end_time,
      valid_from: ctrl.currentAvailability.valid_from || new Date().toISOString().split('T')[0]
    };
    
    professionalAvailabilityService.updateAvailability(ctrl.professionalId, data)
      .then(function(result) {
        console.log('Availability updated', result);
        
        // Update the local availability data
        var index = ctrl.availability.findIndex(function(item) {
          return item.day_of_week === ctrl.currentAvailability.day_of_week;
        });
        
        if (index >= 0) {
          ctrl.availability[index] = result;
          ctrl.selectedDay = result;
          ctrl.currentAvailability = angular.copy(result);
        }
        
        ctrl.success = 'Availability updated successfully';
        
        // Notify parent component
        if (ctrl.onUpdate) {
          ctrl.onUpdate({availability: ctrl.availability});
        }
      })
      .catch(function(error) {
        console.error('Error updating availability', error);
        ctrl.error = 'Failed to update availability. Please try again.';
      })
      .finally(function() {
        ctrl.isSubmitting = false;
      });
  };
  
  // Cancel editing
  ctrl.cancelEdit = function() {
    ctrl.selectedDay = null;
    ctrl.currentAvailability = null;
    ctrl.error = null;
    ctrl.success = null;
  };
  
  // Helper to check if a day has availability set
  ctrl.hasAvailability = function(day) {
    return day.start_time && day.end_time;
  };
}