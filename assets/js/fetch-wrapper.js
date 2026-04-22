// Global fetch wrapper to automatically include credentials for API calls
(function() {
    const originalFetch = window.fetch;
    
    window.fetch = function(url, options = {}) {
        // If URL is an API endpoint, ensure credentials are included
        if (typeof url === 'string' && url.includes('api/')) {
            // Auto-add credentials: 'include' if not already set
            if (!options.credentials) {
                options.credentials = 'include';
            }
        }
        
        return originalFetch.call(this, url, options);
    };
    
    // Copy all properties from original fetch
    Object.setPrototypeOf(window.fetch, originalFetch);
})();
