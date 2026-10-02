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

  constructor(private a: AuthService, private r: Router) {}

  fillDemo(username: string) {
    this.u = username;
    this.p = 'admin123';
    this.error = '';
  }

  submit() {
    this.error = '';
    if (!this.u.trim()) {
      this.error = 'Please enter User ID (e.g. admin or superadmin)';
      return;
    }
    if (!this.p.trim()) {
      this.error = 'Please enter Password (any password, e.g. admin123)';
      return;
    }

    if (this.a.login(this.u, this.p)) {
      this.r.navigateByUrl('/dashboard');
    } else {
      this.error = 'Invalid User ID. Use "admin" or "superadmin" with any password.';
    }
  }
}