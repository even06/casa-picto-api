angular.module('casaPictoApp')
  .factory('httpInterceptor', ['$q', '$location', '$window', function($q, $location, $window) {
    return {
      // Add authorization token to outgoing requests
      request: function(config) {
        // Instead of using authService, get the token directly from localStorage
        var token = $window.localStorage.getItem('casa_picto_token');
        
        if (token) {
          // Don't add token to authentication requests (login/logout)
          if (!config.url.includes('/api/auth/login') && !config.url.includes('/api/auth/logout')) {
            config.headers.Authorization = 'Bearer ' + token;
          }
        }
        
        return config;
      },
      
      // Handle response errors
      responseError: function(rejection) {
        // If unauthorized (401) or forbidden (403), redirect to login
        if (rejection.status === 401 || rejection.status === 403) {
          // Only redirect to login if not already on login page
          if ($location.path() !== '/login') {
            // Clear the token directly instead of using authService
            $window.localStorage.removeItem('casa_picto_token');
            $window.localStorage.removeItem('casa_picto_user');
            $location.path('/login');
          }
        }
        
        return $q.reject(rejection);
      }
    };
  }]);