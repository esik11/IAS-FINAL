package com.example.iasauth.dialogs;

import android.app.Dialog;
import android.content.Context;
import android.os.Bundle;
import android.os.CountDownTimer;
import android.util.Log;
import android.view.View;
import android.view.Window;
import android.widget.Button;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import com.example.iasauth.OTPManager;
import com.example.iasauth.R;
import com.example.iasauth.SessionManager;
import com.example.iasauth.services.EmailService;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;

public class OTPVerificationDialog extends Dialog {
    private static final String TAG = "OTPVerificationDialog";
    private OTPManager otpManager;
    private SessionManager sessionManager;
    private EmailService emailService;
    private String email;
    private OnOTPVerifiedListener listener;
    private CountDownTimer timer;
    private TextView otpTimer;
    private Button resendButton;
    private Button verifyButton;
    private Button useBackupCodeButton;
    private ProgressBar progressBar;
    private TextInputLayout otpInputLayout;
    private TextInputEditText otpInput;
    private TextView otpMessage;

    public interface OnOTPVerifiedListener {
        void onOTPVerified();
    }

    public OTPVerificationDialog(Context context, String email, OnOTPVerifiedListener listener) {
        super(context);
        this.email = email;
        this.listener = listener;
        this.otpManager = new OTPManager(context);
        this.sessionManager = new SessionManager(context);
        this.emailService = new EmailService();
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        try {
            requestWindowFeature(Window.FEATURE_NO_TITLE);
            setContentView(R.layout.dialog_otp_verification);

            // Initialize views
            initializeViews();

            otpMessage.setText("We're sending an OTP to your email: " + email);
            
            // Initial state
            setLoadingState(true);
            sendOTP();

            setupClickListeners();

            // Prevent dialog from being dismissed by tapping outside
            setCanceledOnTouchOutside(false);
        } catch (Exception e) {
            Log.e(TAG, "Error in onCreate", e);
            Toast.makeText(getContext(), "Error initializing OTP dialog", Toast.LENGTH_LONG).show();
            dismiss();
        }
    }

    private void initializeViews() {
        try {
            otpInputLayout = findViewById(R.id.otpInputLayout);
            otpInput = findViewById(R.id.otpInput);
            otpTimer = findViewById(R.id.otpTimer);
            resendButton = findViewById(R.id.resendButton);
            verifyButton = findViewById(R.id.verifyButton);
            useBackupCodeButton = findViewById(R.id.useBackupCodeButton);
            progressBar = findViewById(R.id.progressBar);
            otpMessage = findViewById(R.id.otpMessage);
        } catch (Exception e) {
            Log.e(TAG, "Error initializing views", e);
            throw e;
        }
    }

    private void setupClickListeners() {
        verifyButton.setOnClickListener(v -> {
            String inputOTP = otpInput.getText().toString().trim();
            if (inputOTP.isEmpty()) {
                otpInputLayout.setError("Please enter the OTP");
                return;
            }

            if (otpManager.verifyOTP(inputOTP)) {
                // Create session after successful OTP verification
                sessionManager.createSession(email);
                if (listener != null) {
                    listener.onOTPVerified();
                }
                dismiss();
            } else {
                if (otpManager.isOTPExpired()) {
                    Toast.makeText(getContext(), "OTP has expired. Please request a new one.", Toast.LENGTH_SHORT).show();
                    otpInputLayout.setError("OTP has expired");
                } else {
                    otpInputLayout.setError("Invalid OTP");
                    Toast.makeText(getContext(), "Invalid OTP. Please try again.", Toast.LENGTH_SHORT).show();
                }
            }
        });

        resendButton.setOnClickListener(v -> {
            setLoadingState(true);
            sendOTP();
        });

        useBackupCodeButton.setOnClickListener(v -> {
            try {
                dismiss();
                MFABackupDialog backupDialog = new MFABackupDialog(getContext(), email, () -> {
                    // Create session after successful backup code verification
                    sessionManager.createSession(email);
                    if (listener != null) {
                        listener.onOTPVerified();
                    }
                });
                backupDialog.show();
            } catch (Exception e) {
                Log.e(TAG, "Error showing backup dialog", e);
                Toast.makeText(getContext(), "Error showing backup code dialog", Toast.LENGTH_LONG).show();
            }
        });
    }

    private void setLoadingState(boolean isLoading) {
        if (progressBar != null && resendButton != null && verifyButton != null && useBackupCodeButton != null) {
            progressBar.setVisibility(isLoading ? View.VISIBLE : View.GONE);
            resendButton.setEnabled(!isLoading);
            verifyButton.setEnabled(!isLoading);
            useBackupCodeButton.setEnabled(!isLoading);
        }
    }

    private void sendOTP() {
        try {
            String otp = otpManager.generateOTP();
            otpManager.storeOTP(email, otp);
            
            emailService.sendOTP(email, otp, new EmailService.EmailCallback() {
                @Override
                public void onSuccess() {
                    if (isShowing()) {
                        setLoadingState(false);
                        startOTPTimer();
                        Toast.makeText(getContext(), "OTP sent successfully!", Toast.LENGTH_SHORT).show();
                    }
                }

                @Override
                public void onFailure(String error) {
                    if (isShowing()) {
                        Log.e(TAG, "Failed to send OTP: " + error);
                        setLoadingState(false);
                        Toast.makeText(getContext(), error, Toast.LENGTH_LONG).show();
                    }
                }
            });
        } catch (Exception e) {
            Log.e(TAG, "Error sending OTP", e);
            setLoadingState(false);
            Toast.makeText(getContext(), "Error sending OTP", Toast.LENGTH_LONG).show();
        }
    }

    private void startOTPTimer() {
        if (timer != null) {
            timer.cancel();
        }

        if (resendButton != null && otpTimer != null) {
            resendButton.setEnabled(false);
            long remainingTime = otpManager.getRemainingTime();

            timer = new CountDownTimer(remainingTime, 1000) {
                @Override
                public void onTick(long millisUntilFinished) {
                    if (isShowing() && otpTimer != null) {
                        int minutes = (int) (millisUntilFinished / 60000);
                        int seconds = (int) ((millisUntilFinished % 60000) / 1000);
                        otpTimer.setText(String.format("Time remaining: %02d:%02d", minutes, seconds));
                    }
                }

                @Override
                public void onFinish() {
                    if (isShowing()) {
                        if (otpTimer != null) {
                            otpTimer.setText("OTP has expired");
                        }
                        if (resendButton != null) {
                            resendButton.setEnabled(true);
                        }
                    }
                }
            }.start();
        }
    }

    @Override
    protected void onStop() {
        super.onStop();
        if (timer != null) {
            timer.cancel();
        }
    }
} 