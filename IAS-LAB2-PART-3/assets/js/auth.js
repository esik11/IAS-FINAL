import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-app.js";
import { getAuth, createUserWithEmailAndPassword, signInWithEmailAndPassword, updateProfile, sendEmailVerification } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";
import { getDatabase, ref, set } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-database.js";
import { firebaseConfig } from './firebase-config.js';

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getDatabase(app);

// Show message function
const showMessage = (message, type = 'error') => {
    const messageBox = document.getElementById('messageBox');
    if (messageBox) {
        messageBox.textContent = message;
        messageBox.classList.remove('d-none', 'alert-success', 'alert-danger');
        messageBox.classList.add('alert', `alert-${type === 'error' ? 'danger' : 'success'}`);
    }
};

// Show/hide loading spinner
const toggleLoading = (show = true) => {
    const spinner = document.getElementById('loadingSpinner');
    const submitBtn = document.querySelector('button[type="submit"]');
    
    if (spinner) spinner.classList.toggle('d-none', !show);
    if (submitBtn) submitBtn.disabled = show;
};

// Generate 8 backup codes
const generateBackupCodes = () => {
    const codes = [];
    for (let i = 0; i < 8; i++) {
        const code = Math.random().toString(36).substring(2, 10).toUpperCase();
        codes.push(code);
    }
    return codes;
};

// Display backup codes to user
const displayBackupCodes = (codes) => {
    const container = document.querySelector('.container');
    const form = document.getElementById('registerForm');
    
    if (form) {
        form.style.display = 'none';
        
        container.innerHTML = `
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h3 class="text-center mb-0">Registration Successful!</h3>
                </div>
                <div class="card-body">
                    <h4 class="text-center">Your Backup Codes</h4>
                    <p class="text-center text-muted mb-4">Store these codes safely. You'll need them if you can't access your email.</p>
                    <div class="row row-cols-2 g-3 mb-4">
                        ${codes.map(code => `
                            <div class="col">
                                <div class="border rounded p-2 text-center bg-light">
                                    <code>${code}</code>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    <div class="d-grid gap-2">
                        <button onclick="window.print()" class="btn btn-secondary">Print Codes</button>
                        <a href="login.php" class="btn btn-primary">Continue to Login</a>
                    </div>
                </div>
            </div>
        `;
    }
};

// Handle registration form submission
const handleRegister = async (e) => {
    e.preventDefault();
    toggleLoading(true);
    
    try {
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        // Basic validation
        if (!name || !email || !password || !confirmPassword) {
            throw new Error('All fields are required');
        }
        
        if (password !== confirmPassword) {
            throw new Error('Passwords do not match');
        }
        
        // Create user in Firebase
        const userCredential = await createUserWithEmailAndPassword(auth, email, password);
        const user = userCredential.user;
        
        // Update profile with name
        await updateProfile(user, { displayName: name });
        
        // Generate backup codes
        const backupCodes = generateBackupCodes();
        
        // Store user and backup codes in database
        const response = await fetch('/IAS-FINAL/IAS-LAB2-PART-3/app/api/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                email: email,
                password: password,
                firebase_uid: user.uid,
                backup_codes: backupCodes
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to store user data');
        }
        
        // Send email verification
        await sendEmailVerification(user);
        
        // Display backup codes
        displayBackupCodes(backupCodes);
        
    } catch (error) {
        console.error('Registration error:', error);
        showMessage(error.message, 'error');
    } finally {
        toggleLoading(false);
    }
};

// Handle login form submission
const handleLogin = async (e) => {
    e.preventDefault();
    toggleLoading(true);
    
    try {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        if (!email || !password) {
            throw new Error('Email and password are required');
        }
        
        const userCredential = await signInWithEmailAndPassword(auth, email, password);
        const user = userCredential.user;
        
        if (!user.emailVerified) {
            throw new Error('Please verify your email first');
        }
        
        // Login successful, redirect to OTP verification
        window.location.href = '/IAS-FINAL/IAS-LAB2-PART-3/app/views/auth/verify-otp.php';
        
    } catch (error) {
        console.error('Login error:', error);
        showMessage(error.message, 'error');
    } finally {
        toggleLoading(false);
    }
};

// Add event listeners when the document is loaded
document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
});

