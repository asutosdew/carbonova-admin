import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss'
})
export class LoginComponent {
  step: 'credentials' | '2fa' = 'credentials';
  u = '';
  p = '';
  totpCode = '';
  tempToken = '';
  userPreview: any = null;

  show = false;
  error = '';
  loading = false;

  constructor(private a: AuthService, private r: Router) {}

  submit() {
    this.error = '';
    if (!this.u.trim()) {
      this.error = 'Please enter User ID or Email.';
      return;
    }
    if (!this.p.trim()) {
      this.error = 'Please enter Password.';
      return;
    }

    this.loading = true;
    this.a.login(this.u, this.p).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.requires_2fa) {
          // Switch to Google Authenticator verification screen
          this.step = '2fa';
          this.tempToken = res.temp_token || '';
          this.userPreview = res.user || null;
          this.totpCode = '';
          this.error = '';
        } else if (res.success) {
          this.r.navigateByUrl('/dashboard');
        } else {
          this.error = res.message || 'Invalid User ID / Email or Password.';
        }
      },
      error: (err) => {
        this.loading = false;
        this.error = err?.error?.message || err?.message || 'Login request failed. Check server connection.';
      }
    });
  }

  submit2fa() {
    const code = this.totpCode.trim();
    if (!code) {
      this.error = 'Please enter the 6-digit Authenticator code.';
      return;
    }
    if (code.length !== 6 || !/^\d{6}$/.test(code)) {
      this.error = 'Please enter all 6 digits shown in your Authenticator app.';
      return;
    }

    this.loading = true;
    this.error = '';
    this.a.verify2fa(this.tempToken, code).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          this.r.navigateByUrl('/dashboard');
        } else {
          this.error = res.message || 'Invalid Authenticator code. Please check your app.';
        }
      },
      error: (err) => {
        this.loading = false;
        this.error = err?.error?.message || err?.message || 'Verification failed. Please try again.';
      }
    });
  }

  onCodeChange() {
    this.error = '';
    // Auto-submit when exactly 6 digits are typed
    if (this.totpCode.trim().length === 6 && /^\d{6}$/.test(this.totpCode.trim())) {
      this.submit2fa();
    }
  }

  backToCredentials() {
    this.step = 'credentials';
    this.totpCode = '';
    this.tempToken = '';
    this.error = '';
    this.loading = false;
  }
}