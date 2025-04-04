// src/app/components/professionals/professional-form.component.js
import templateHtml from './professional-form.template.html?raw';

angular.module('casaPictoApp')
  .component('professionalForm', {
    template: templateHtml,
    bindings: {
      resolve: '<',
      close: '&',
      dismiss: '&'
    },
    controller: ['$scope', '$http', 'apiConfigService', ProfessionalFormController]
  });

function ProfessionalFormController($scope, $http, apiConfigService) {
  var ctrl = this;
  
  // UI states
  ctrl.isSubmitting = false;
  ctrl.formError = null;
  ctrl.showPassword = false;
  
  // Initialize component
  ctrl.$onInit = function() {
    // Get resolved values
    ctrl.professional = ctrl.resolve.professional || {
      name: '',
      specialty: '',
      username: '',
      password: '',
      is_active: true
    };
    
    ctrl.editMode = ctrl.resolve.editMode || false;
    
    // Debugging
    console.log('Professional Form Component Initialized', {
      professional: ctrl.professional,
      editMode: ctrl.editMode
    });
  };
  
  // Toggle password visibility
  ctrl.togglePassword = function() {
    ctrl.showPassword = !ctrl.showPassword;
  };
  
  // Cancel the form
  ctrl.cancel = function() {
    console.log('Dismissing modal');
    ctrl.dismiss();
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
        console.log('Professional saved successfully', response.data);
        // Close modal with the updated professional
        ctrl.close({$value: response.data.data});
      } else {
        ctrl.formError = 'Error saving professional. Please try again.';
      }
    }).catch(function(error) {
      console.error('Error saving professional', error);
      ctrl.formError = error.data && error.data.error ? 
        error.data.error.message : 
        'Failed to save professional. Please try again.';
    }).finally(function() {
      ctrl.isSubmitting = false;
    });
  };
}