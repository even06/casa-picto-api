// src/app/components/patients/patient-form.component.js
import templateHtml from './patient-form.template.html?raw';

angular.module('casaPictoApp')
  .component('patientForm', {
    template: templateHtml,
    bindings: {
      resolve: '<',
      close: '&',
      dismiss: '&'
    },
    controller: ['$http', 'apiConfigService', 'insuranceService', PatientFormController]
  });

function PatientFormController($http, apiConfigService, insuranceService) {
  var ctrl = this;
  
  // UI states
  ctrl.isSubmitting = false;
  ctrl.formError = null;
  ctrl.insuranceCompanies = [];
  
  // Initialize component
  ctrl.$onInit = function() {
    // Get resolved values
    ctrl.patient = ctrl.resolve.patient || {
      name: '',
      email: '',
      phone: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      insurance_company_id: null,
      insurance_number: '',
      has_cud: false,
      cud_type: '',
      is_active: true,
      professionals: []
    };
    
    ctrl.editMode = ctrl.resolve.editMode || false;
    ctrl.professionals = ctrl.resolve.professionals || [];
    
    // If we're editing, make sure professionals is an array of IDs for the multiselect
    if (ctrl.editMode && ctrl.patient.professionals) {
      // Convert professionals array to array of IDs for the multiselect
      ctrl.selectedProfessionals = ctrl.patient.professionals.map(function(pro) {
        return pro.id;
      });
    } else {
      ctrl.selectedProfessionals = [];
    }
    
    // Load insurance companies
    loadInsuranceCompanies();
    
    // Debugging
    console.log('Patient Form Component Initialized', {
      patient: ctrl.patient,
      editMode: ctrl.editMode,
      professionals: ctrl.professionals
    });
  };
  
  // Load available insurance companies
  function loadInsuranceCompanies() {
    insuranceService.getInsuranceCompanies()
      .then(function(data) {
        ctrl.insuranceCompanies = data.companies || [];
      })
      .catch(function(error) {
        console.error('Error loading insurance companies', error);
      });
  }
  
  // Cancel the form
  ctrl.cancel = function() {
    ctrl.dismiss();
  };
  
  // Save patient (create or update)
  ctrl.savePatient = function(form) {
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
      // Update existing patient
      url = apiConfigService.buildUrl('patients/update') + '?id=' + ctrl.patient.id;
      method = 'PUT';
    } else {
      // Create new patient
      url = apiConfigService.buildUrl('patients/create');
      method = 'POST';
    }
    
    // Prepare data for API
    var requestData = {
      name: ctrl.patient.name,
      email: ctrl.patient.email,
      phone: ctrl.patient.phone,
      emergency_contact_name: ctrl.patient.emergency_contact_name,
      emergency_contact_phone: ctrl.patient.emergency_contact_phone,
      insurance_company_id: ctrl.patient.insurance_company_id,
      insurance_number: ctrl.patient.insurance_number,
      has_cud: ctrl.patient.has_cud,
      cud_type: ctrl.patient.cud_type,
      is_active: ctrl.patient.is_active,
      professionals: ctrl.selectedProfessionals
    };
    
    $http({
      method: method,
      url: url,
      data: requestData
    }).then(function(response) {
      if (response.data && response.data.success) {
        console.log('Patient saved successfully', response.data);
        // Close modal with the updated patient
        ctrl.close({$value: response.data.data});
      } else {
        ctrl.formError = 'Error saving patient. Please try again.';
      }
    }).catch(function(error) {
      console.error('Error saving patient', error);
      ctrl.formError = error.data && error.data.error ? 
        error.data.error.message : 
        'Failed to save patient. Please try again.';
    }).finally(function() {
      ctrl.isSubmitting = false;
    });
  };
}