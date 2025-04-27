package com.example.iasauth;

import android.content.Intent;
import android.os.Bundle;
import android.text.TextUtils;
import android.util.Log;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.example.iasauth.dialogs.OTPVerificationDialog;
import com.google.android.material.textfield.TextInputLayout;
import com.google.firebase.auth.FirebaseAuth;
import com.google.firebase.auth.FirebaseUser;
import com.example.iasauth.security.RateLimitManager;

public class LoginActivity extends AppCompatActivity {
    private static final String TAG = "LoginActivity";
    private EditText emailInput;
    private EditText passwordInput;
    private TextInputLayout emailLayout;
    private TextInputLayout passwordLayout;
    private Button loginButton;
    private TextView registerLink;
    private SessionManager sessionManager;
    private FirebaseAuth mAuth;
    private RateLimitManager rateLimitManager;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_login);

        sessionManager = new SessionManager(this);
        mAuth = FirebaseAuth.getInstance();
        rateLimitManager = new RateLimitManager(this);

        // Initialize views
        emailInput = findViewById(R.id.emailInput);
        passwordInput = findViewById(R.id.passwordInput);
        emailLayout = findViewById(R.id.emailLayout);
        passwordLayout = findViewById(R.id.passwordLayout);
        loginButton = findViewById(R.id.loginButton);
        registerLink = findViewById(R.id.registerLink);

        // Check if user is already logged in
        FirebaseUser currentUser = mAuth.getCurrentUser();
        if (currentUser != null && currentUser.isEmailVerified() && sessionManager.isLoggedIn()) {
            startMainActivity();
            return;
        }

        // Set click listeners
        loginButton.setOnClickListener(v -> handleLogin());
        registerLink.setOnClickListener(v -> {
            Intent intent = new Intent(LoginActivity.this, RegisterActivity.class);
            startActivity(intent);
            finish();
        });
    }

    private void handleLogin() {
        String email = emailInput.getText().toString().trim();
        String password = passwordInput.getText().toString().trim();

        // Check rate limiting first
        if (rateLimitManager.isRateLimited(email)) {
            long remainingTime = rateLimitManager.getBlockTimeRemaining(email);
            long minutes = remainingTime / (60 * 1000);
            showError("Too many login attempts. Please try again in " + minutes + " minutes.");
            return;
        }

        // Validate email
        if (TextUtils.isEmpty(email)) {
            emailLayout.setError("Please enter your email");
            return;
        }

        if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            emailLayout.setError("Please enter a valid email address");
            return;
        }

        // Validate password
        if (TextUtils.isEmpty(password)) {
            passwordLayout.setError("Please enter your password");
            return;
        }

        // Clear any previous errors
        emailLayout.setError(null);
        passwordLayout.setError(null);

        // Show loading state
        setLoadingState(true);

        try {
            // Sign in with Firebase using actual password
            mAuth.signInWithEmailAndPassword(email, password)
                    .addOnCompleteListener(this, task -> {
                        try {
                            if (task.isSuccessful()) {
                                FirebaseUser user = mAuth.getCurrentUser();
                                if (user != null) {
                                    if (user.isEmailVerified()) {
                                        // Record successful login attempt
                                        rateLimitManager.recordLoginAttempt(email, true);
                                        
                                        // Show OTP verification dialog
                                        runOnUiThread(() -> {
                                            try {
                                                OTPVerificationDialog dialog = new OTPVerificationDialog(this, email, () -> {
                                                    startMainActivity();
                                                });
                                                dialog.show();
                                            } catch (Exception e) {
                                                Log.e(TAG, "Error showing OTP dialog", e);
                                                showError("Error showing OTP verification. Please try again.");
                                            }
                                        });
                                    } else {
                                        // Record failed attempt
                                        rateLimitManager.recordLoginAttempt(email, false);
                                        
                                        showError("Please verify your email first.");
                                        // Send verification email again
                                        user.sendEmailVerification()
                                            .addOnCompleteListener(verificationTask -> {
                                                if (verificationTask.isSuccessful()) {
                                                    Toast.makeText(LoginActivity.this,
                                                        "Verification email sent again.",
                                                        Toast.LENGTH_SHORT).show();
                                                }
                                            });
                                    }
                                }
                            } else {
                                // Record failed attempt
                                rateLimitManager.recordLoginAttempt(email, false);
                                
                                String errorMessage = task.getException() != null ? 
                                    task.getException().getMessage() : "Login failed";
                                Log.e(TAG, "Login failed: " + errorMessage);
                                
                                int remainingAttempts = rateLimitManager.getRemainingAttempts(email);
                                String attemptsMessage = remainingAttempts > 0 ? 
                                    " (" + remainingAttempts + " attempts remaining)" : 
                                    " (Account will be temporarily blocked)";
                                    
                                showError("Login failed: " + errorMessage + attemptsMessage);
                                
                                // Clear password field on failed login
                                passwordInput.setText("");
                            }
                        } catch (Exception e) {
                            Log.e(TAG, "Error in login completion", e);
                            showError("An error occurred during login. Please try again.");
                        } finally {
                            // Reset button state
                            setLoadingState(false);
                        }
                    });
        } catch (Exception e) {
            Log.e(TAG, "Error initiating login", e);
            showError("An error occurred. Please try again.");
            setLoadingState(false);
        }
    }

    private void setLoadingState(boolean loading) {
        runOnUiThread(() -> {
            loginButton.setEnabled(!loading);
            loginButton.setText(loading ? "Logging in..." : "Login");
            emailInput.setEnabled(!loading);
            passwordInput.setEnabled(!loading);
        });
    }

    private void startMainActivity() {
        Intent intent = new Intent(LoginActivity.this, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
    }

    private void showError(String message) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
    }

    @Override
    protected void onResume() {
        super.onResume();
        FirebaseUser currentUser = mAuth.getCurrentUser();
        if (currentUser != null && currentUser.isEmailVerified() && sessionManager.isLoggedIn()) {
            startMainActivity();
        }
    }
} 