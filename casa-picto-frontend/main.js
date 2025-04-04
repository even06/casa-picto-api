// Import styles
import 'bootstrap/dist/css/bootstrap.min.css';
import './src/assets/css/styles.css';

// Import libraries
import angular from 'angular';
import 'angular-route';
import 'angular-sanitize';
import 'angular-animate';
import 'bootstrap';

// Import our app module
import './src/app/app';

// Import services
import './src/app/services/api-config.service';
import './src/app/services/auth.service';
import './src/app/services/professional.service';
import './src/app/services/http-interceptor.service';

// Import components
import './src/app/components/login/login.component';
import './src/app/components/professionals/professionals.component';