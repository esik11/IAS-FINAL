package com.example.iasauth.adapters;

import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageButton;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.example.iasauth.R;

import java.util.List;

public class BackupCodesAdapter extends RecyclerView.Adapter<BackupCodesAdapter.BackupCodeViewHolder> {
    private List<String> backupCodes;
    private Context context;

    public BackupCodesAdapter(Context context, List<String> backupCodes) {
        this.context = context;
        this.backupCodes = backupCodes;
    }

    @NonNull
    @Override
    public BackupCodeViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_backup_code, parent, false);
        return new BackupCodeViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull BackupCodeViewHolder holder, int position) {
        String code = backupCodes.get(position);
        holder.backupCodeText.setText(code);
        
        holder.copyButton.setOnClickListener(v -> {
            ClipboardManager clipboard = (ClipboardManager) context.getSystemService(Context.CLIPBOARD_SERVICE);
            ClipData clip = ClipData.newPlainText("Backup Code", code);
            clipboard.setPrimaryClip(clip);
            Toast.makeText(context, "Code copied to clipboard", Toast.LENGTH_SHORT).show();
        });
    }

    @Override
    public int getItemCount() {
        return backupCodes.size();
    }

    static class BackupCodeViewHolder extends RecyclerView.ViewHolder {
        TextView backupCodeText;
        ImageButton copyButton;

        BackupCodeViewHolder(@NonNull View itemView) {
            super(itemView);
            backupCodeText = itemView.findViewById(R.id.backupCodeText);
            copyButton = itemView.findViewById(R.id.copyButton);
        }
    }
} 