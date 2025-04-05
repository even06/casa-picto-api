// src/app/components/patients/patients.component.js
import templateHtml from './patients.template.html?raw';

angular.module('casaPictoApp')
  .component('patients', {
    template: templateHtml,
    controller: ['$location', 'authService', 'patientService', 'professionalService', '$uibModal', PatientsController]
  });

function PatientsController($location, authService, patientService, professionalService, $uibModal) {
  var ctrl = this;
  
  // Make Math available to the template
  ctrl.Math = window.Math;
  
  // Current user
  ctrl.currentUser = authService.getCurrentUser();
  
  // Data
  ctrl.patients = [];
  ctrl.professionals = [];
  ctrl.insuranceCompanies = [];
  ctrl.pagination = {
    total: 0,
    page: 1,
    limit: 10,
    total_pages: 0
  };
  
  // Filters
  ctrl.filters = {
    search: '',
    insuranceId: '',
    isActive: null,
    professionalId: ''
  };
  
  // UI states
  ctrl.isLoading = true;
  ctrl.error = null;
  
  // Initialize component
  ctrl.$onInit = function() {
    // Load professionals for filter
    professionalService.getProfessionals({limit: 100})
      .then(function(data) {
        ctrl.professionals = data.professionals || [];
      })
      .catch(function(error) {
        console.error('Error loading professionals', error);
      });
    
    // Load initial patients data
    ctrl.loadPatients();
  };
  
  // It creates an array of page numbers for pagination
  ctrl.getPageNumbers = function() {
    var totalPages = ctrl.pagination.total_pages || 0;
    var pages = [];
    for (var i = 1; i <= totalPages; i++) {
      pages.push(i);
    }
    return pages;
  };
  
  // Load patients with current filters and pagination
  ctrl.loadPatients = function() {
    ctrl.isLoading = true;
    ctrl.error = null;
    
    var params = {
      page: ctrl.pagination.page,
      limit: ctrl.pagination.limit
    };
    
    // Add filters if they have values
    if (ctrl.filters.search) {
      params.search = ctrl.filters.search;
    }
    
    if (ctrl.filters.insuranceId) {
      params.insuranceId = ctrl.filters.insuranceId;
    }
    
    if (ctrl.filters.isActive !== null) {
      params.isActive = ctrl.filters.isActive;
    }
    
    if (ctrl.filters.professionalId) {
      params.professionalId = ctrl.filters.professionalId;
    }
    
    patientService.getPatients(params)
      .then(function(data) {
        ctrl.patients = data.patients;
        ctrl.pagination = data.pagination;
      })
      .catch(function(error) {
        ctrl.error = error.data && error.data.error ? 
          error.data.error.message : 
          'Failed to load patients. Please try again.';
      })
      .finally(function() {
        ctrl.isLoading = false;
      });
  };
  
  // Open modal for adding a new patient
  ctrl.openAddModal = function() {
    console.log('Opening add patient modal');
    
    var modalInstance = $uibModal.open({
      animation: true,
      component: 'patientForm',
      backdrop: 'static',
      size: 'lg',
      resolve: {
        patient: function() {
          return null;
        },
        editMode: function() {
          return false;
        },
        professionals: function() {
          return ctrl.professionals;
        }
      }
    });

    modalInstance.result.then(function(patient) {
      // Handle when modal is closed with a result
      console.log('Modal closed with result', patient);
      ctrl.loadPatients();
    }, function() {
      // Handle when modal is dismissed
      console.log('Modal dismissed');
    });
  };
  
  // View patient details
  ctrl.viewPatient = function(id) {
    $location.path('/patients/' + id);
  };
  
  // Open modal for editing an existing patient
  ctrl.openEditModal = function(patient, event) {
    // Stop event propagation to prevent navigation
    if (event) {
      event.stopPropagation();
    }
    
    console.log('Opening edit patient modal', patient);
    
    var modalInstance = $uibModal.open({
      animation: true,
      component: 'patientForm',
      backdrop: 'static',
      size: 'lg',
      resolve: {
        patient: function() {
          return angular.copy(patient);
        },
        editMode: function() {
          return true;
        },
        professionals: function() {
          return ctrl.professionals;
        }
      }
    });

    modalInstance.result.then(function(updatedPatient) {
      // Handle when modal is closed with a result
      console.log('Modal closed with result', updatedPatient);
      ctrl.loadPatients();
    }, function() {
      // Handle when modal is dismissed
      console.log('Modal dismissed');
    });
  };
  
  // Apply filters
  ctrl.applyFilters = function() {
    ctrl.pagination.page = 1; // Reset to first page when filtering
    ctrl.loadPatients();
  };
  
  // Reset filters
  ctrl.resetFilters = function() {
    ctrl.filters = {
      search: '',
      insuranceId: '',
      isActive: null,
      professionalId: ''
    };
    ctrl.applyFilters();
  };
  
  // Change page
  ctrl.changePage = function(page) {
    ctrl.pagination.page = page;
    ctrl.loadPatients();
  };
  
  // Logout
  ctrl.logout = function() {
    authService.logout()
      .then(function() {
        $location.path('/login');
      });
  };
}