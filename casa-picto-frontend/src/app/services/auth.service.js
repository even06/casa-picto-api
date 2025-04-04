angular.module('casaPictoApp')
  .service('authService', ['$http', '$window', 'apiConfigService', function($http, $window, apiConfigService) {
    var service = this;
    
    // Store current user information
    var currentUser = null;
    
    // Local storage keys
    var TOKEN_KEY = 'casa_picto_token';
    var USER_KEY = 'casa_picto_user';
    
    // Initialize from localStorage
    try {
      var savedToken = $window.localStorage.getItem(TOKEN_KEY);
      var savedUser = $window.localStorage.getItem(USER_KEY);
      
      if (savedToken && savedUser) {
        currentUser = JSON.parse(savedUser);
      }
    } catch (e) {
      console.error('Error loading auth data from localStorage', e);
      // Clear any potentially corrupted data
      $window.localStorage.removeItem(TOKEN_KEY);
      $window.localStorage.removeItem(USER_KEY);
    }
    
    // Login method
    service.login = function(credentials) {
      return $http.post(apiConfigService.buildUrl('auth/login'), credentials)
        .then(function(response) {
          if (response.data && response.data.success) {
            // Save token and user data
            var userData = response.data.data;
            $window.localStorage.setItem(TOKEN_KEY, userData.token);
            $window.localStorage.setItem(USER_KEY, JSON.stringify(userData.user));
            currentUser = userData.user;
            return userData;
          } else {
            throw new Error('Invalid response from server');
          }
        });
    };
    
    // Logout method
    service.logout = function() {
      // Remove token from localStorage before making logout request
      $window.localStorage.removeItem(TOKEN_KEY);
      $window.localStorage.removeItem(USER_KEY);
      currentUser = null;
      
      // Optional: Call logout API if needed
      return $http.post(apiConfigService.buildUrl('auth/logout'));
    };
    
    // Get current authentication token
    service.getToken = function() {
      return $window.localStorage.getItem(TOKEN_KEY);
    };
    
    // Get current user
    service.getCurrentUser = function() {
      return currentUser;
    };
    
    // Check if user is authenticated
    service.isAuthenticated = function() {
      return !!service.getToken();
    };
    
    // Check if user has specific role
    service.hasRole = function(role) {
      return currentUser && currentUser.role === role;
    };
    
    return service;
  }]);