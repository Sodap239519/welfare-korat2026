import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';

// Sanctum SPA — same-origin cookies
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Reasonable timeout
window.axios.defaults.timeout = 30000;
