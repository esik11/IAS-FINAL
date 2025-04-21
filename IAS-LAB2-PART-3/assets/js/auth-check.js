// Debug logging function
const debugLog = (message, data = null) => {
    console.log(`[Auth Check] ${message}`, data || '');
};

debugLog('Script initialized');

// Inactivity monitoring
let inactivityTimeout;
let lastActivity = Date.now();
const INACTIVE_TIMEOUT = 60 * 1000; // 1 minute
let remainingTime = INACTIVE_TIMEOUT;

// Function to format time remaining
const formatTimeRemaining = (ms) => {
    const seconds = Math.ceil(ms / 1000);
    return `${seconds} seconds`;
};

// Update timer display in console
const updateTimerDisplay = () => {
    const now = Date.now();
    const timeSinceLastActivity = now - lastActivity;
    remainingTime = Math.max(0, INACTIVE_TIMEOUT - timeSinceLastActivity);
    
    debugLog('Inactivity timer', {
        lastActivity: new Date(lastActivity).toLocaleTimeString(),
        timeRemaining: formatTimeRemaining(remainingTime),
        status: 'active'
    });
};

// Reset inactivity timer
const resetInactivityTimer = () => {
    clearTimeout(inactivityTimeout);
    lastActivity = Date.now();
    
    debugLog('Activity detected - Timer reset', {
        lastActivity: new Date(lastActivity).toLocaleTimeString(),
        timeRemaining: formatTimeRemaining(INACTIVE_TIMEOUT)
    });

    inactivityTimeout = setTimeout(() => {
        debugLog('Session expired due to inactivity - Logging out');
        window.location.href = 'logout.php';
    }, INACTIVE_TIMEOUT);
};

// Session check function
const checkSession = async () => {
    try {
        const response = await fetch('../../api/check-session.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        debugLog('Session check', {
            authenticated: data.authenticated,
            email: data.email,
            timestamp: new Date(data.timestamp * 1000).toLocaleTimeString()
        });

        if (!data.authenticated) {
            debugLog('Session expired - Redirecting to login');
            window.location.href = 'login.php';
        }
    } catch (error) {
        debugLog('Session check error', error.message);
    }
};

// Initialize monitoring if on dashboard
if (document.querySelector('.dashboard-container')) {
    debugLog('Dashboard detected - Initializing activity monitoring');

    // Monitor user activity
    const activityEvents = ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    
    activityEvents.forEach(event => {
        document.addEventListener(event, resetInactivityTimer);
    });

    // Start the initial timer
    resetInactivityTimer();

    // Display timer updates every 5 seconds
    setInterval(updateTimerDisplay, 5000);

    // Check session every 30 seconds
    setInterval(checkSession, 30000);

    // Initial session check
    checkSession();

    debugLog('Monitoring initialized', {
        inactivityTimeout: `${INACTIVE_TIMEOUT/1000} seconds`,
        sessionCheckInterval: '30 seconds',
        timerDisplayInterval: '5 seconds'
    });
} 