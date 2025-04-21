import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-app.js";
import { getAuth, signInWithEmailAndPassword, sendEmailVerification } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";
import { getDatabase, ref, get, set } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-database.js";
import { firebaseConfig } from './firebase-config.js';
import { generateOTP, storeOTP, verifyOTP, sendOTP } from './email-otp.js';

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getDatabase(app);

// Add this helper function at the top
const debug = (message, data = null) => {
    console.log(`[DEBUG] ${message}`, data || '');
};

// Show message function
const showMessage = (elementId, message, type = 'danger') => {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = message;
        element.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        element.classList.add('alert-' + type);
        element.classList.remove('d-none');
    }
};

// Function to start OTP timer
const startOTPTimer = () => {
    let timeLeft = 60; // 1 minute in seconds
    const timerDisplay = document.getElementById('otpTimer');
    const timerProgress = document.getElementById('timerProgress');
    const resendButton = document.getElementById('resendOTP');
    
    // Clear any existing timer
    if (window.otpTimer) {
        clearInterval(window.otpTimer);
    }
    
    // Update display immediately
    timerDisplay.textContent = `${timeLeft}s`;
    timerProgress.style.width = '100%';
    timerProgress.setAttribute('aria-valuenow', 100);
    resendButton.disabled = true;

    window.otpTimer = setInterval(() => {
        timeLeft--;
        
        // Update timer text
        timerDisplay.textContent = `${timeLeft}s`;
        
        // Update progress bar
        const progressPercentage = (timeLeft / 60) * 100;
        timerProgress.style.width = `${progressPercentage}%`;
        timerProgress.setAttribute('aria-valuenow', progressPercentage);
        
        // Change progress bar color as time decreases
        if (timeLeft <= 10) {
            timerProgress.classList.remove('bg-info', 'bg-warning');
            timerProgress.classList.add('bg-danger');
        } else if (timeLeft <= 30) {
            timerProgress.classList.remove('bg-info', 'bg-danger');
            timerProgress.classList.add('bg-warning');
        }
        
        if (timeLeft <= 0) {
            clearInterval(window.otpTimer);
            timerDisplay.textContent = 'Expired';
            timerProgress.style.width = '0%';
            timerProgress.setAttribute('aria-valuenow', 0);
            resendButton.disabled = false;
        }
    }, 1000);

    return window.otpTimer;
};

// Verify backup code
const verifyBackupCode = async (inputCode, email) => {
    try {
        console.log(`Verifying backup code for ${email}`);
        
        // First try to verify from the database
        const response = await fetch('/IAS-LAB2-PART-3/app/api/auth/verify-backup-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                code: inputCode
            })
        });
        
        const data = await response.json();
        console.log('Backup code verification response:', data);
        
        if (data.success) {
            return true;
        }
        
        // Fallback to localStorage for development/testing
        // This should be removed in production
        if (window.location.hostname === 'localhost') {
            console.warn('Using fallback localStorage for backup codes');
            const backupCodes = JSON.parse(localStorage.getItem(`backupCodes_${email}`) || '[]');
            
            if (backupCodes.includes(inputCode)) {
                // Remove the used backup code
                const updatedCodes = backupCodes.filter(code => code !== inputCode);
                localStorage.setItem(`backupCodes_${email}`, JSON.stringify(updatedCodes));
                return true;
            }
        }
        
        return false;
    } catch (error) {
        console.error('Error verifying backup code:', error);
        
        // Fallback for development only
        if (window.location.hostname === 'localhost') {
            console.warn('Error in API, using localStorage fallback');
            try {
                const backupCodes = JSON.parse(localStorage.getItem(`backupCodes_${email}`) || '[]');
                if (backupCodes.includes(inputCode)) {
                    const updatedCodes = backupCodes.filter(code => code !== inputCode);
                    localStorage.setItem(`backupCodes_${email}`, JSON.stringify(updatedCodes));
                    return true;
                }
            } catch (e) {
                console.error('Fallback verification failed:', e);
            }
        }
        
        return false;
    }
};

// Show OTP form
const showOTPForm = (email, container) => {
    // Clear any existing timer before showing the form
    if (window.otpTimer) {
        clearInterval(window.otpTimer);
    }
    
    container.innerHTML = `
        <h3 class="text-center mb-3">Enter OTP</h3>
        <div id="messageBox" class="alert d-none"></div>
        <div class="alert alert-info">
            We've sent an OTP to your email: ${email}
        </div>
        <form id="otpForm" class="mb-3">
            <div class="mb-3">
                <label for="otp">Enter 6-digit OTP</label>
                <input type="text" 
                    class="form-control text-center" 
                    id="otp" 
                    name="otp" 
                    inputmode="numeric" 
                    maxlength="6" 
                    required 
                    pattern="[0-9]{6}" 
                    title="Please enter 6 digits (0-9)" 
                    placeholder="6-digit OTP"
                    autocomplete="off"
                    style="letter-spacing: 0.5em; font-size: 1.2em;">
                <div class="form-text">Enter the 6-digit code sent to your email</div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
            
            <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span>OTP expires in:</span>
                    <span id="otpTimer">60s</span>
                </div>
                <div class="progress" style="height: 12px;">
                    <div id="timerProgress" class="progress-bar bg-info progress-bar-striped progress-bar-animated" 
                        role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            
            <button type="button" class="btn btn-secondary w-100 mt-3" id="resendOTP" disabled>Resend OTP</button>
            <button type="button" class="btn btn-link w-100" id="useBackupCode">Use backup code instead</button>
        </form>
    `;

    const timer = startOTPTimer();
    
    // Add OTP input formatting - allow only digits and auto-submit when 6 digits are entered
    const otpInput = document.getElementById('otp');
    otpInput.addEventListener('input', function(e) {
        // Replace any non-digit character with empty string
        this.value = this.value.replace(/[^0-9]/g, '');
        
        // Auto-submit when 6 digits are entered
        if (this.value.length === 6) {
            document.getElementById('otpForm').dispatchEvent(new Event('submit'));
        }
    });

    // Handle OTP verification
    const otpForm = document.getElementById('otpForm');
    otpForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitButton = otpForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        
        try {
            const inputOTP = document.getElementById('otp').value;
            
            // Additional client-side validation
            if (!/^\d{6}$/.test(inputOTP)) {
                showMessage('messageBox', 'Please enter exactly 6 digits for the OTP.', 'warning');
                submitButton.disabled = false;
                return;
            }
            
            const verification = verifyOTP(inputOTP);
            console.log('OTP verification result:', verification);

            if (verification.valid) {
                console.log('Verifying OTP and creating session...');
                const response = await fetch('/IAS-LAB2-PART-3/app/api/verify-auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email }),
                    credentials: 'include'
                });

                const data = await response.json();
                console.log('Auth response:', data);

                if (data.success) {
                    // Clear timer before redirecting
                    clearInterval(timer);
                    console.log('Authentication successful, redirecting...');
                    showMessage('messageBox', 'Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.replace('dashboard.php');
                    }, 1000);
                } else {
                    console.error('Authentication failed:', data.message);
                    showMessage('messageBox', 'Authentication failed: ' + (data.message || 'Unknown error'), 'danger');
                    submitButton.disabled = false;
                }
            } else {
                if (verification.message.includes('expired')) {
                    showMessage('messageBox', 'OTP expired. Please request a new one.', 'warning');
                } else {
                    showMessage('messageBox', verification.message || 'Invalid OTP. Please try again.', 'danger');
                }
                submitButton.disabled = false;
            }
        } catch (error) {
            console.error('Auth error:', error);
            showMessage('messageBox', 'Authentication failed: ' + error.message, 'danger');
            submitButton.disabled = false;
        }
    });

    // Handle backup code button
    const useBackupCodeBtn = document.getElementById('useBackupCode');
    useBackupCodeBtn.addEventListener('click', () => {
        clearInterval(timer);
        showBackupCodeForm(email, container);
    });

    // Handle resend OTP
    const resendButton = document.getElementById('resendOTP');
    resendButton.addEventListener('click', async () => {
        resendButton.disabled = true;
        const timerDisplay = document.getElementById('otpTimer');
        const timerProgress = document.getElementById('timerProgress');
        timerDisplay.textContent = 'Sending...';
        timerProgress.style.width = '0%';
        
        // Clear any existing timer
        if (window.otpTimer) {
            clearInterval(window.otpTimer);
        }
        
        try {
            showMessage('messageBox', 'Sending new OTP...', 'info');
            const newOTP = generateOTP();
            console.log('Resending new OTP:', newOTP);
            
            const sent = await sendOTP(email, newOTP);
            
            if (sent) {
                showMessage('messageBox', 'New OTP sent! Check your email.', 'success');
                
                // Reset and restart timer
                let timeLeft = 60; // 1 minute in seconds
                
                // Update timer display immediately
                timerDisplay.textContent = `${timeLeft}s`;
                timerProgress.style.width = '100%';
                timerProgress.setAttribute('aria-valuenow', 100);
                timerProgress.classList.remove('bg-warning', 'bg-danger');
                timerProgress.classList.add('bg-info');
                
                // Start new timer
                window.otpTimer = setInterval(() => {
                    timeLeft--;
                    timerDisplay.textContent = `${timeLeft}s`;
                    
                    // Update progress bar
                    const progressPercentage = (timeLeft / 60) * 100;
                    timerProgress.style.width = `${progressPercentage}%`;
                    timerProgress.setAttribute('aria-valuenow', progressPercentage);
                    
                    // Change progress bar color as time decreases
                    if (timeLeft <= 10) {
                        timerProgress.classList.remove('bg-info', 'bg-warning');
                        timerProgress.classList.add('bg-danger');
                    } else if (timeLeft <= 30) {
                        timerProgress.classList.remove('bg-info', 'bg-danger');
                        timerProgress.classList.add('bg-warning');
                    }
                    
                    if (timeLeft <= 0) {
                        clearInterval(window.otpTimer);
                        timerDisplay.textContent = 'Expired';
                        timerProgress.style.width = '0%';
                        timerProgress.setAttribute('aria-valuenow', 0);
                        resendButton.disabled = false;
                    }
                }, 1000);
            } else {
                showMessage('messageBox', 'Failed to send OTP. Please try again.', 'danger');
                timerDisplay.textContent = 'Failed';
                resendButton.disabled = false;
            }
        } catch (error) {
            console.error('Resend OTP error:', error);
            showMessage('messageBox', 'Failed to send OTP: ' + error.message, 'danger');
            timerDisplay.textContent = 'Error';
            resendButton.disabled = false;
        }
    });
};

// Show backup code form
const showBackupCodeForm = (email, container) => {
    container.innerHTML = `
        <h3 class="text-center mb-3">Enter Backup Code</h3>
        <div id="messageBox" class="alert d-none"></div>
        <div class="alert alert-info">
            <p>Enter one of your backup codes to authenticate.</p>
            <p class="mb-0"><small>These are the codes that were provided during registration.</small></p>
        </div>
        <form id="backupForm">
            <div class="mb-3">
                <input type="text" class="form-control" id="backupCode" required placeholder="Enter backup code">
            </div>
            <button type="submit" class="btn btn-primary w-100">Verify Code</button>
            <button type="button" class="btn btn-secondary w-100 mt-2" id="backToOTP">Back to OTP</button>
        </form>
    `;

    document.getElementById('backupForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        
        // Show progress message
        showMessage('messageBox', 'Verifying backup code...', 'info');
        
        const code = document.getElementById('backupCode').value;
        
        try {
            const isValid = await verifyBackupCode(code, email);
            
            if (isValid) {
                showMessage('messageBox', 'Backup code verified! Creating session...', 'success');
                
                try {
                    // Create server-side session
                    const response = await fetch('/IAS-LAB2-PART-3/app/api/verify-auth.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ email }),
                        credentials: 'include'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showMessage('messageBox', 'Login successful! Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.replace('dashboard.php');
                        }, 1000);
                    } else {
                        throw new Error(data.message || 'Authentication failed');
                    }
                } catch (error) {
                    console.error('Session error:', error);
                    showMessage('messageBox', 'Authentication failed: ' + error.message, 'danger');
                    submitButton.disabled = false;
                }
            } else {
                showMessage('messageBox', 'Invalid backup code. Please try again.', 'danger');
                submitButton.disabled = false;
            }
        } catch (error) {
            console.error('Backup code verification error:', error);
            showMessage('messageBox', 'Verification error: ' + error.message, 'danger');
            submitButton.disabled = false;
        }
    });

    document.getElementById('backToOTP').addEventListener('click', () => {
        showOTPForm(email, container);
    });
};

// Login function
const loginUser = async (email, password) => {
    try {
        // Check if login container exists
        const loginContainer = document.querySelector('.card-body');
        if (!loginContainer) {
            console.error('Login container not found!');
            alert('UI Error: Login container not found. Please refresh the page.');
            return;
        }

        // Show loading spinner and hide form
        const loadingSpinner = document.getElementById('loadingSpinner');
        const loginForm = document.getElementById('loginForm');
        if (loadingSpinner) loadingSpinner.classList.remove('d-none');
        
        // Disable login button
        const submitButton = loginForm.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            const userCredential = await signInWithEmailAndPassword(auth, email, password);
            const user = userCredential.user;

            if (!user.emailVerified) {
                showMessage('messageBox', 'Please verify your email first.', 'danger');
                if (submitButton) submitButton.disabled = false;
                if (loadingSpinner) loadingSpinner.classList.add('d-none');
                return;
            }

            // Generate and send OTP
            const otp = generateOTP();
            console.log('Sending OTP to:', email, 'OTP:', otp);
            
            const sent = await sendOTP(email, otp);
            console.log('OTP sent result:', sent);

            if (sent) {
                showOTPForm(email, loginContainer);
            } else {
                showMessage('messageBox', 'Failed to send OTP. Please try again.', 'danger');
                if (submitButton) submitButton.disabled = false;
                if (loadingSpinner) loadingSpinner.classList.add('d-none');
            }
        } catch (authError) {
            console.error('Auth error:', authError);
            if (submitButton) submitButton.disabled = false;
            if (loadingSpinner) loadingSpinner.classList.add('d-none');
            
            let errorMessage = 'Login failed: ';
            switch(authError.code) {
                case 'auth/user-not-found':
                    errorMessage += 'Email not registered.';
                    break;
                case 'auth/wrong-password':
                    errorMessage += 'Invalid password.';
                    break;
                case 'auth/invalid-credential':
                    errorMessage += 'Invalid credentials.';
                    break;
                default:
                    errorMessage += authError.message;
            }
            
            showMessage('messageBox', errorMessage, 'danger');
        }
    } catch (error) {
        console.error('Login error:', error);
        showMessage('messageBox', error.message, 'danger');
    }
};

// Initialize login form
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            await loginUser(email, password);
        });
    }
});

// Add this debug function
const debugLog = (message, data = null) => {
    console.log(`[Debug] ${message}`, data || '');
}; 