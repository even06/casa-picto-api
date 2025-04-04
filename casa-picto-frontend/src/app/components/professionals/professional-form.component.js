// src/app/components/professionals/professional-form.component.js
import templateHtml from './professional-form.template.html?raw';

angular.module('casaPictoApp')
  .component('professionalForm', {
    template: templateHtml,
    controller: ['$http', 'apiConfigService', ProfessionalFormController],
    bindings: {
      professional: '<',  // Input: professional object to edit
      editMode: '<',      // Input: true if editing, false if adding
      onSave: '&',        // Output: callback when professional is saved
      onCancel: '&'       // Output: callback when cancel is clicked
    }
  });

function ProfessionalFormController($http, apiConfigService) {
  var ctrl = this;
  
  // UI states
  ctrl.isSubmitting = false;
  ctrl.formError = null;
  ctrl.showPassword = false;
  
  // Initialize component
  ctrl.$onInit = function() {
    // Default values for new professional
    if (!ctrl.editMode) {
      ctrl.professional = {
        name: '',
        specialty: '',
        username: '',
        password: '',
        is_active: true
      };
    }
  };
  
  // Handle input changes when professional binding updates
  ctrl.$onChanges = function(changes) {
    if (changes.professional && changes.professional.currentValue) {
      // Clone the object to avoid modifying the parent
      ctrl.professional = angular.copy(changes.professional.currentValue);
      
      // Ensure Boolean type for is_active (in case it comes as 0/1)
      ctrl.professional.is_active = !!ctrl.professional.is_active;
    }
  };
  
  // Toggle password visibility
  ctrl.togglePassword = function() {
    ctrl.showPassword = !ctrl.showPassword;
  };
  
  // Save professional (create or update)
  ctrl.saveProfessional = function(form) {
    if (form.$invalid) {
      // Mark all inputs as touched to display validation errors
      angular.forEach(form.$error, function(field) {
        angular.forEach(field, function(errorField) {
          errorField.$setTouched();
        });
      });
      return;
    }
    
    ctrl.isSubmitting = true;
    ctrl.formError = null;
    
    var url, method;
    
    if (ctrl.editMode) {
      // Update existing professional
      url = apiConfigService.buildUrl('users/update') + '?id=' + ctrl.professional.id;
      method = 'PUT';
    } else {
      // Create new professional
      url = apiConfigService.buildUrl('users/create');
      method = 'POST';
    }
    
    // Prepare data for API
    var requestData = {
      name: ctrl.professional.name,
      specialty: ctrl.professional.specialty,
      is_active: ctrl.professional.is_active
    };
    
    // Add username/password only for new professionals
    if (!ctrl.editMode) {
      requestData.username = ctrl.professional.username;
      requestData.password = ctrl.professional.password;
      requestData.role = 'professional';  // Always create as professional
    }
    
    $http({
      method: method,
      url: url,
      data: requestData
    }).then(function(response) {
      if (response.data && response.data.success) {
        // Notify parent component
        ctrl.onSave({ professional: response.data.data });
      } else {
        ctrl.formError = 'Error saving professional. Please try again.';
      }
    }).catch(function(error) {
      ctrl.formError = error.data && error.data.error ? 
        error.data.error.message : 
        'Failed to save professional. Please try again.';
    }).finally(function() {
      ctrl.isSubmitting = false;
    });
  };
}