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
  u = 'admin';
  p = 'admin123';
  show = false;
  error = '';
  loading = false;

  constructor(private a: AuthService, private r: Router) {}

  fillDemo(username: string) {
    this.u = username;
    this.p = 'admin123';
    this.error = '';
  }

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
        if (res.success) {
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
}