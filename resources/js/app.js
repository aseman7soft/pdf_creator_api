import * as bootstrap from 'bootstrap';

// The panel behaviours rely on the global Bootstrap object, both in bundled
// mode (this file) and in the non-Vite fallback (bootstrap.bundle.min.js).
window.bootstrap = bootstrap;

import './panel.js';

export default bootstrap;
