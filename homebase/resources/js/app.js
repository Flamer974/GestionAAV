import './bootstrap';
import axios from 'axios';

window.axios = axios;

// Configuration CSRF globale
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

console.log('HomeBase loaded • PHP ' + window.PHP_VERSION);