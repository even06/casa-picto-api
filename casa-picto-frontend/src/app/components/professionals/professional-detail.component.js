// src/app/components/professionals/professional-detail.component.js
import templateHtml from './professional-detail.template.html?raw';

angular.module('casaPictoApp')
  .component('professionalDetail', {
    template: templateHtml,
    controller: ['$routeParams', '$location', 'professionalService', 'authService', '$uibModal', ProfessionalDetailController]
  });

function ProfessionalDetailController($routeParams, $location, professionalService, authService, $uibModal) {
  var ctrl = this;
  
  // Current user
  ctrl.currentUser = authService.getCurrentUser();
  
  // Data
  ctrl.professional = null;
  ctrl.professionalId = $routeParams.id;
  
  // UI states
  ctrl.isLoading = true;
  ctrl.error = null;
  ctrl.activeTab = 'info'; // Default active tab
  
  // Initialize component
  ctrl.$onInit = function() {
    ctrl.loadProfessional();
  };
  
  // Load professional details
  ctrl.loadProfessional = function() {
    ctrl.isLoading = true;
    ctrl.error = null;
    
    professionalService.getProfessional(ctrl.professionalId)
      .then(function(data) {
        ctrl.professional = data;
      })
      .catch(function(error) {
        console.error('Error loading professional', error);
        ctrl.error = error.data && error.data.error ? 
          error.data.error.message : 
          'Failed to load professional details. Please try again.';
      })
      .finally(function() {
        ctrl.isLoading = false;
      });
  };
  
  // Change active tab
  ctrl.setActiveTab = function(tab) {
    ctrl.activeTab = tab;
  };
  
  // Handle availability updates
  ctrl.onAvailabilityUpdate = function(availability) {
    // Refresh the professional data
    ctrl.loadProfessional();
  };
  
  // Go back to professionals list
  ctrl.goBack = function() {
    $location.path('/professionals');
  };
  
  // Edit professional
  ctrl.editProfessional = function() {
    var modalInstance = $uibModal.open({
      animation: true,
      component: 'professionalForm',
      backdrop: 'static',
      size: 'lg',
      resolve: {
        professional: function() {
          // Include user_id in the professional object for the form
          var professionalData = angular.copy(ctrl.professional);
          professionalData.user_id = professionalData.user_id;
          return professionalData;
        },
        editMode: function() {
          return true;
        }
      }
    });

    modalInstance.result.then(function(updatedProfessional) {
      // Handle when modal is closed with a result
      console.log('Modal closed with result', updatedProfessional);
      ctrl.loadProfessional(); // Reload professional data
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