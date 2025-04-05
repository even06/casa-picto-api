// src/app/components/patients/patient-detail.component.js
import templateHtml from './patient-detail.template.html?raw';

angular.module('casaPictoApp')
  .component('patientDetail', {
    template: templateHtml,
    controller: ['$routeParams', '$location', 'patientService', 'authService', '$uibModal', PatientDetailController]
  });

function PatientDetailController($routeParams, $location, patientService, authService, $uibModal) {
  var ctrl = this;
  
  // Current user
  ctrl.currentUser = authService.getCurrentUser();
  
  // Data
  ctrl.patient = null;
  ctrl.patientId = $routeParams.id;
  
  // UI states
  ctrl.isLoading = true;
  ctrl.error = null;
  ctrl.activeTab = 'info'; // Default active tab
  
  // Initialize component
  ctrl.$onInit = function() {
    ctrl.loadPatient();
  };
  
  // Load patient details
  ctrl.loadPatient = function() {
    ctrl.isLoading = true;
    ctrl.error = null;
    
    patientService.getPatient(ctrl.patientId)
      .then(function(data) {
        ctrl.patient = data;
      })
      .catch(function(error) {
        console.error('Error loading patient', error);
        ctrl.error = error.data && error.data.error ? 
          error.data.error.message : 
          'Failed to load patient details. Please try again.';
      })
      .finally(function() {
        ctrl.isLoading = false;
      });
  };
  
  // Change active tab
  ctrl.setActiveTab = function(tab) {
    ctrl.activeTab = tab;
  };
  
  // Go back to patients list
  ctrl.goBack = function() {
    $location.path('/patients');
  };
  
  // Edit patient
  ctrl.editPatient = function() {
    var modalInstance = $uibModal.open({
      animation: true,
      component: 'patientForm',
      backdrop: 'static',
      size: 'lg',
      resolve: {
        patient: function() {
          return angular.copy(ctrl.patient);
        },
        editMode: function() {
          return true;
        },
        professionals: function() {
          // We would ideally get these from a service
          // For now, pass through what's in the patient detail
          return ctrl.patient.professionals.map(function(pro) {
            return {
              id: pro.id,
              name: pro.name,
              specialty: pro.specialty
            };
          });
        }
      }
    });

    modalInstance.result.then(function(updatedPatient) {
      // Handle when modal is closed with a result
      console.log('Modal closed with result', updatedPatient);
      ctrl.loadPatient(); // Reload patient data
    }, function() {
      // Handle when modal is dismissed
      console.log('Modal dismissed');
    });
  };
  
  // Logout
  ctrl.logout = function() {
    authService.logout()
      .then(function() {
        $location.path('/login');
      });
  };
}