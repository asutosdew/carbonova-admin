import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { PageHeaderComponent } from '../../shared/page-header.component';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  templateUrl: './settings.component.html',
  styleUrl: './settings.component.scss'
})
export class SettingsComponent implements OnInit {
  company = 'Carbonova World';
  email = 'support@carbonovaworld.com';
  approval = true;

  // 2FA state
  is2faEnabled = false;
  checking2fa = false;
  settingUp2fa = false;
  qrCodeUrl = '';
  secretKey = '';
  confirmCode = '';
  twofaLoading = false;
  twofaMessage = '';
  twofaError = '';

  constructor(public auth: AuthService) {}

  ngOnInit(): void {
    this.check2faStatus();
  }

  check2faStatus(): void {
    this.checking2fa = true;
    this.auth.get2faStatus().subscribe({
      next: (res) => {
        this.checking2fa = false;
        this.is2faEnabled = !!res.two_factor_enabled;
      },
      error: () => {
        this.checking2fa = false;
        this.is2faEnabled = false;
      }
    });
  }

  startSetup2fa(): void {
    this.twofaLoading = true;
    this.twofaError = '';
    this.twofaMessage = '';
    this.confirmCode = '';
    this.auth.setup2fa().subscribe({
      next: (res) => {
        this.twofaLoading = false;
        if (res.success && res.qr_code_url) {
          this.qrCodeUrl = res.qr_code_url;
          this.secretKey = res.secret || '';
          this.settingUp2fa = true;
        } else {
          this.twofaError = res.message || 'Failed to generate 2FA QR code.';
        }
      },
      error: (err) => {
        this.twofaLoading = false;
        this.twofaError = err?.message || 'Error initializing 2FA setup.';
      }
    });
  }

  confirm2fa(): void {
    const code = this.confirmCode.trim();
    if (!code || code.length !== 6) {
      this.twofaError = 'Please enter the 6-digit verification code from the app.';
      return;
    }
    this.twofaLoading = true;
    this.twofaError = '';
    this.auth.confirm2fa(code).subscribe({
      next: (res) => {
        this.twofaLoading = false;
        if (res.success) {
          this.is2faEnabled = true;
          this.settingUp2fa = false;
          this.twofaMessage = 'Google Authenticator 2FA enabled successfully!';
        } else {
          this.twofaError = res.message || 'Invalid code. Please check Authenticator app.';
        }
      },
      error: (err) => {
        this.twofaLoading = false;
        this.twofaError = err?.message || 'Verification failed.';
      }
    });
  }

  disable2fa(): void {
    if (!confirm('Are you sure you want to disable Google Authenticator 2FA? This will decrease your account security.')) {
      return;
    }
    this.twofaLoading = true;
    this.twofaError = '';
    this.twofaMessage = '';
    this.auth.disable2fa().subscribe({
      next: (res) => {
        this.twofaLoading = false;
        if (res.success) {
          this.is2faEnabled = false;
          this.twofaMessage = 'Two-factor authentication has been disabled.';
        } else {
          this.twofaError = res.message || 'Failed to disable 2FA.';
        }
      },
      error: (err) => {
        this.twofaLoading = false;
        this.twofaError = err?.message || 'Failed to disable 2FA.';
      }
    });
  }

  closeSetupModal(): void {
    this.settingUp2fa = false;
    this.confirmCode = '';
    this.twofaError = '';
  }
}