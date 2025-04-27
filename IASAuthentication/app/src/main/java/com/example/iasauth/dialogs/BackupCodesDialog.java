package com.example.iasauth.dialogs;

import android.app.Dialog;
import android.content.Context;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.Window;
import android.widget.Button;
import android.widget.TextView;
import android.widget.Toast;

import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.example.iasauth.R;
import com.example.iasauth.adapters.BackupCodesAdapter;

import java.util.List;

public class BackupCodesDialog extends Dialog {
    private List<String> backupCodes;
    private OnBackupCodesSavedListener listener;
    private Handler handler;
    private TextView countdownText;
    private int countdown = 10;

    public interface OnBackupCodesSavedListener {
        void onBackupCodesSaved();
    }

    public BackupCodesDialog(Context context, List<String> backupCodes, OnBackupCodesSavedListener listener) {
        super(context);
        this.backupCodes = backupCodes;
        this.listener = listener;
        this.handler = new Handler(Looper.getMainLooper());
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        setContentView(R.layout.dialog_backup_codes);

        RecyclerView recyclerView = findViewById(R.id.backupCodesRecyclerView);
        countdownText = findViewById(R.id.countdownText);
        recyclerView.setLayoutManager(new LinearLayoutManager(getContext()));
        recyclerView.setAdapter(new BackupCodesAdapter(getContext(), backupCodes));

        // Start countdown
        startCountdown();

        // Prevent dialog from being dismissed by tapping outside
        setCanceledOnTouchOutside(false);
    }

    private void startCountdown() {
        handler.post(new Runnable() {
            @Override
            public void run() {
                if (countdown > 0) {
                    countdownText.setText("Redirecting to login in " + countdown + " seconds...");
                    countdown--;
                    handler.postDelayed(this, 1000);
                } else {
                    if (listener != null) {
                        listener.onBackupCodesSaved();
                    }
                    dismiss();
                }
            }
        });
    }

    @Override
    protected void onStop() {
        super.onStop();
        handler.removeCallbacksAndMessages(null);
    }
} 