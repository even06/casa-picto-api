// src/app/components/professionals/professionals.component.js
import templateHtml from './professionals.template.html?raw';

angular.module('casaPictoApp')
  .component('professionals', {
    template: templateHtml,
    controller: ['$location', 'authService', 'professionalService', ProfessionalsController]
  });

function ProfessionalsController($location, authService, professionalService) {
  var ctrl = this;
  
  // Make Math and Array available to the template
  ctrl.Math = window.Math;
  ctrl.Array = window.Array;
  
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
  
  // View professional details
  ctrl.viewProfessional = function(id) {
    $location.path('/professionals/' + id);
  };
  
  // Logout
  ctrl.logout = function() {
    authService.logout()
      .then(function() {
        $location.path('/login');
      });
  };
}