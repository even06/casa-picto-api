angular.module('casaPictoApp')
  .service('apiConfigService', [function() {
    var service = this;
    
    // API URL configuration - change this to switch between environments
    var apiConfig = {
      // Local development with Vite proxy (uses the proxy set in vite.config.js)
      useProxy: false,
      
      // Direct API URL (used when not using proxy)
      apiUrl: 'https://www.casapicto.com/casapictov2/api',
      
      // API version if needed
      apiVersion: ''
    };
    
    // Get the base URL for API requests
    service.getBaseUrl = function() {
      if (apiConfig.useProxy) {
        // When using Vite proxy, we use a relative URL
        return '/api';
      } else {
        // When accessing API directly
        return apiConfig.apiUrl + (apiConfig.apiVersion ? '/' + apiConfig.apiVersion : '');
      }
    };
    
    // Build a full URL for a specific API endpoint
    service.buildUrl = function(endpoint) {
      // Make sure endpoint doesn't start with a slash if we're appending
      if (endpoint.startsWith('/')) {
        endpoint = endpoint.substring(1);
      }
      
      return service.getBaseUrl() + '/' + endpoint;
    };
    
    return service;
  }]);