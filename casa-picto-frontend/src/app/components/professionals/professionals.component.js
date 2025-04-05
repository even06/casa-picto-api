// src/app/components/professionals/professionals.component.js
import templateHtml from './professionals.template.html?raw';

angular.module('casaPictoApp')
  .component('professionals', {
    template: templateHtml,
    controller: ['$location', 'authService', 'professionalService', '$uibModal', ProfessionalsController]
  });

function ProfessionalsController($location, authService, professionalService, $uibModal) {
  var ctrl = this;
  
  // Make Math available to the template
  ctrl.Math = window.Math;
  
  // Current user
  ctrl.currentUser = authService.getCurrentUser();
  
  // Data
  ctrl.professionals = [];
  ctrl.specialties = [];
  ctrl.pagination = {
    total: 0,
    page: 1,
    limit: 10,
    totalPages: 0
  };
  
  // Filters
  ctrl.filters = {
    search: '',
    specialty: '',
    isActive: null
  };
  
  // UI states
  ctrl.isLoading = true;
  ctrl.error = null;
  
  // Initialize component
  ctrl.$onInit = function() {
    ctrl.loadProfessionals();
  };
  
  // Open modal for adding a new professional
  ctrl.openAddModal = function() {
    console.log('Opening add professional modal');
    
    var modalInstance = $uibModal.open({
      animation: true,
      component: 'professionalForm',
      backdrop: 'static',
      size: 'lg',
      resolve: {
        professional: function() {
          return null;
        },
        editMode: function() {
          return false;
        }
      }
    });

    modalInstance.result.then(function(professional) {
      // Handle when modal is closed with a result
      console.log('Modal closed with result', professional);
      ctrl.loadProfessionals();
    }, function() {
      // Handle when modal is dismissed
      console.log('Modal dismissed');
    });
  };
  
  ctrl.viewProfessional = function(id) {
    $location.path('/professionals/' + id);
  };
  
  // Edit professional in the detail view
  ctrl.editProfessional = function() {
    ctrl.openEditModal(ctrl.professional);
  };

  // Open modal for editing an existing professional
  ctrl.openEditModal = function(professional, event) {
    // Stop event propagation to prevent navigation
    if (event) {
      event.stopPropagation();
    }
    
    console.log('Opening edit professional modal', professional);
    
    var modalInstance = $uibModal.open({
      animation: true,
      component: 'professionalForm',
      backdrop: 'static',
      size: 'lg',
      resolve: {
        professional: function() {
          return angular.copy(professional);
        },
        editMode: function() {
          return true;
        }
      }
    });

    modalInstance.result.then(function(updatedProfessional) {
      // Handle when modal is closed with a result
      console.log('Modal closed with result', updatedProfessional);
      ctrl.loadProfessionals();
    }, function() {
      // Handle when modal is dismissed
      console.log('Modal dismissed');
    });
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
  
  // Load professionals with current filters and pagination
  ctrl.loadProfessionals = function() {
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
    
    if (ctrl.filters.specialty) {
      params.specialty = ctrl.filters.specialty;
    }
    
    if (ctrl.filters.isActive !== null) {
      params.isActive = ctrl.filters.isActive;
    }
    
    professionalService.getProfessionals(params)
      .then(function(data) {
        ctrl.professionals = data.professionals;
        ctrl.specialties = data.specialties;
        ctrl.pagination = data.pagination;
      })
      .catch(function(error) {
        ctrl.error = error.data && error.data.error ? 
          error.data.error.message : 
          'Failed to load professionals. Please try again.';
      })
      .finally(function() {
        ctrl.isLoading = false;
      });
  };
  
  // Apply filters
  ctrl.applyFilters = function() {
    ctrl.pagination.page = 1; // Reset to first page when filtering
    ctrl.loadProfessionals();
  };
  
  // Reset filters
  ctrl.resetFilters = function() {
    ctrl.filters = {
      search: '',
      specialty: '',
      isActive: null
    };
    ctrl.applyFilters();
  };
  
  // Change page
  ctrl.changePage = function(page) {
    ctrl.pagination.page = page;
    ctrl.loadProfessionals();
  };
  
  // Logout
  ctrl.logout = function() {
    authService.logout()
      .then(function() {
        $location.path('/login');
      });
  };
}