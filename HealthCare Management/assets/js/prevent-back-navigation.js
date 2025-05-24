// Prevent back/forward navigation after logout
(function() {
    // Disable caching for this page
    window.onpageshow = function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    };

    // Clear browser history and replace current URL
    window.history.pushState(null, document.title, window.location.href);
    
    // Handle back/forward button
    window.onpopstate = function() {
        window.history.pushState(null, document.title, window.location.href);
        // Redirect to login page if session is expired
        fetch('auth/session_check.php')
            .then(response => response.json())
            .then(data => {
                if (!data.logged_in) {
                    window.location.href = 'auth/Login.php';
                }
            });
    };
})();
