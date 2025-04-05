// Initialize the main app module first
angular.module('casaPictoApp', ['ngRoute', 'ngSanitize', 'ngAnimate', 'ui.bootstrap']);

// Then configure it separately
angular.module('casaPictoApp')
  .config(['$routeProvider', '$httpProvider', '$uibModalProvider', function($routeProvider, $httpProvider, $uibModalProvider) {
    // Configure routes
    $routeProvider
      .when('/login', {
        template: '<login></login>',
        unauthenticated: true
      })
      .when('/professionals', {
        template: '<professionals></professionals>',
        authenticated: true
      })
      .when('/professionals/:id', {
        template: '<professional-detail></professional-detail>',
        authenticated: true
      })
      .otherwise({
        redirectTo: '/login'
      });
    
    // Add auth interceptor
    $httpProvider.interceptors.push('httpInterceptor');
    
    // Configure ui-bootstrap modals
    $uibModalProvider.options.backdrop = true;
    $uibModalProvider.options.keyboard = true;
  }]);

// Run block
angular.module('casaPictoApp')
  .run(['$rootScope', '$location', 'authService', function($rootScope, $location, authService) {
    console.log('AngularJS app is running!');
    
    // Check authentication on route change
    $rootScope.$on('$routeChangeStart', function(event, next) {
      console.log('Route change:', next);
      
      // If route requires authentication and user is not logged in
      if (next.authenticated && !authService.isAuthenticated()) {
        $location.path('/login');
        event.preventDefault();
      }
      
      // If route is for unauthenticated users only and user is logged in
      if (next.unauthenticated && authService.isAuthenticated()) {
        $location.path('/professionals');
        event.preventDefault();
      }
    });
  }]);