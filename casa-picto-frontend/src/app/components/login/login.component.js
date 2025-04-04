// src/app/components/login/login.component.js
import templateHtml from './login.template.html?raw';

angular.module('casaPictoApp')
  .component('login', {
    template: templateHtml,
    controller: ['$location', 'authService', LoginController]
  });

function LoginController($location, authService) {
  var ctrl = this;
  
  // Form data
  ctrl.credentials = {
    username: '',
    password: ''
  };
  
  // UI states
  ctrl.isLoading = false;
  ctrl.error = null;

  ctrl.currentYear = new Date().getFullYear();
  
  // Handle login form submission
  ctrl.login = function() {
    // Reset error and set loading state
    ctrl.error = null;
    ctrl.isLoading = true;
    
    // Call auth service to attempt login
    authService.login(ctrl.credentials)
      .then(function(response) {
        // On success, redirect to homepage
        $location.path('/professionals');
      })
      .catch(function(error) {
        // Handle login errors
        ctrl.error = error.data && error.data.error ? 
          error.data.error.message : 
          'An unexpected error occurred. Please try again.';
      })
      .finally(function() {
        // Reset loading state
        ctrl.isLoading = false;
      });
  };
}