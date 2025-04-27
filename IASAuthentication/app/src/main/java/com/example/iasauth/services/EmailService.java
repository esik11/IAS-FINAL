package com.example.iasauth.services;

import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import java.util.Properties;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import javax.mail.Message;
import javax.mail.MessagingException;
import javax.mail.PasswordAuthentication;
import javax.mail.Session;
import javax.mail.Transport;
import javax.mail.internet.InternetAddress;
import javax.mail.internet.MimeMessage;

public class EmailService {
    private static final String TAG = "EmailService";
    private static final String FROM_EMAIL = "jeorgeandreielevencionado@gmail.com"; // Replace with your email
    private static final String EMAIL_PASSWORD = "madx kzxk jkqi vhlj"; // Replace with your app password
    private static final String SMTP_HOST = "smtp.gmail.com";
    private static final int SMTP_PORT = 587;

    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private final Handler handler = new Handler(Looper.getMainLooper());

    public interface EmailCallback {
        void onSuccess();
        void onFailure(String error);
    }

    public void sendOTP(String toEmail, String otp, EmailCallback callback) {
        executor.execute(() -> {
            try {
                Properties props = new Properties();
                props.put("mail.smtp.auth", "true");
                props.put("mail.smtp.starttls.enable", "true");
                props.put("mail.smtp.host", SMTP_HOST);
                props.put("mail.smtp.port", SMTP_PORT);

                Session session = Session.getInstance(props, new javax.mail.Authenticator() {
                    protected PasswordAuthentication getPasswordAuthentication() {
                        return new PasswordAuthentication(FROM_EMAIL, EMAIL_PASSWORD);
                    }
                });

                Message message = new MimeMessage(session);
                message.setFrom(new InternetAddress(FROM_EMAIL));
                message.setRecipients(Message.RecipientType.TO, InternetAddress.parse(toEmail));
                message.setSubject("Your OTP Code");
                message.setText("Your OTP code is: " + otp + "\nThis code will expire in 5 minutes.");

                Transport.send(message);
                
                handler.post(() -> {
                    if (callback != null) {
                        callback.onSuccess();
                    }
                });
            } catch (MessagingException e) {
                Log.e(TAG, "Error sending email", e);
                handler.post(() -> {
                    if (callback != null) {
                        callback.onFailure("Failed to send OTP email: " + e.getMessage());
                    }
                });
            } catch (Exception e) {
                Log.e(TAG, "Unexpected error", e);
                handler.post(() -> {
                    if (callback != null) {
                        callback.onFailure("An unexpected error occurred");
                    }
                });
            }
        });
    }
} 