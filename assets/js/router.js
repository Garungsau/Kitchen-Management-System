(function (global) {
    const ROUTES = {
        home: 'index.html',
        login: 'auth/login.html',
        signup: 'signup.html',
        admin: 'admin_dashboard.html',
        employee: 'employee_dashboard.html',
        student: 'employee_dashboard.html',
        kitchen_staff: 'kitchen_staff_dashboard.html',
        kitchen: 'kitchen_staff_dashboard.html'
    };

    function getDashboardByRole(role) {
        return ROUTES[role] || ROUTES.login;
    }

    function goTo(path) {
        window.location.href = path;
    }

    function goToLogin() {
        goTo(ROUTES.login);
    }

    function bootPublicPage(activePage) {
        if (typeof renderNavbar === 'function') {
            renderNavbar('guest', activePage || '');
        }
        if (typeof renderFooter === 'function') {
            renderFooter();
        }
    }

    function bootRolePage(role) {
        if (typeof renderNavbar === 'function') {
            renderNavbar(role);
        }
        if (typeof renderFooter === 'function') {
            renderFooter();
        }
    }

    async function requireAuth(allowedRoles) {
        const res = await fetch('api/check_auth.php', { credentials: 'include' });
        const data = await res.json();

        if (data.status !== 'success') {
            goToLogin();
            return null;
        }

        if (Array.isArray(allowedRoles) && allowedRoles.length > 0 && !allowedRoles.includes(data.role)) {
            goToLogin();
            return null;
        }

        return data;
    }

    async function logout() {
        try {
            await fetch('api/logout.php', { credentials: 'include' });
        } finally {
            goToLogin();
        }
    }

    global.AppRouter = {
        routes: ROUTES,
        getDashboardByRole,
        goTo,
        goToLogin,
        bootPublicPage,
        bootRolePage,
        requireAuth,
        logout,
        // Helper function for API calls with credentials
        apiFetch: async function(url, options = {}) {
            if (!options.credentials) {
                options.credentials = 'include';
            }
            return fetch(url, options);
        }
    };
    
    // Patch fetch to auto-include credentials for API calls
    const _fetch = window.fetch;
    window.fetch = function(resource, init = {}) {
        if (typeof resource === 'string' && resource.includes('api/')) {
            init.credentials = init.credentials || 'include';
        }
        return _fetch.call(window, resource, init);
    };
})(window);
