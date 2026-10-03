import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService, AdminUser, RoleName } from '../../core/auth.service';
import { PageHeaderComponent } from '../../shared/page-header.component';
import { EditModalComponent } from '../../shared/edit-modal/edit-modal.component';
import { PaginationComponent } from '../../shared/pagination/pagination.component';

@Component({
  selector: 'app-users-roles',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, PaginationComponent, EditModalComponent],
  templateUrl: './users-roles.component.html',
  styleUrl: './users-roles.component.scss'
})
export class UsersRolesComponent implements OnInit {
  private readonly auth = inject(AuthService);

  tab: 'users' | 'roles' = 'users';
  showForm = false;
  loading = false;
  submitting = false;
  errorMessage = '';
  successMessage = '';

  page = 1;
  pageSize = 10;
  users: AdminUser[] = [];
  roles: any[] = [];

  newUser = {
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'Operations Admin' as RoleName,
    status: 'Active' as 'Active' | 'Inactive'
  };

  showEdit = false;
  editing: any = null;

  labels: Record<string, string> = {
    dashboard: 'Dashboard',
    members: 'Members',
    packages: 'Packages',
    payments: 'Payments',
    'direct-income': 'Direct Income',
    'level-income': 'Level Income',
    'matrix-income': 'Matrix Income',
    payouts: 'Payouts',
    accounts: 'Farmer Accounts',
    transactions: 'Transactions',
    'users-roles': 'Users & Roles',
    settings: 'Settings',
    shipping: 'Shipping',
    products: 'Products'
  };

  ngOnInit(): void {
    this.roles = this.auth.getRoles();
    this.loadUsers();
  }

  loadUsers(): void {
    this.loading = true;
    this.auth.loadAdminUsers().subscribe({
      next: (list) => {
        this.users = list;
        this.loading = false;
      },
      error: () => {
        this.users = this.auth.getUsers();
        this.loading = false;
      }
    });
  }

  get pagedUsers(): AdminUser[] {
    const start = (this.page - 1) * this.pageSize;
    return this.users.slice(start, start + this.pageSize);
  }

  onPageChange(p: number): void {
    this.page = p;
  }

  onPageSizeChange(size: number): void {
    this.pageSize = size;
    this.page = 1;
  }

  addUser(): void {
    const name = this.newUser.name.trim();
    const username = this.newUser.username.trim();
    if (!name || !username) {
      this.errorMessage = 'Please provide both Name and Username.';
      return;
    }

    this.submitting = true;
    this.errorMessage = '';
    this.successMessage = '';

    const payload = {
      name,
      username,
      email: this.newUser.email.trim(),
      password: this.newUser.password.trim() || 'admin123',
      role: this.newUser.role,
      status: this.newUser.status
    };

    this.auth.saveAdminUser(payload).subscribe({
      next: (res) => {
        this.submitting = false;
        if (res.success) {
          this.successMessage = res.message || 'Admin user created successfully.';
          this.showForm = false;
          this.newUser = {
            name: '',
            username: '',
            email: '',
            password: '',
            role: 'Operations Admin',
            status: 'Active'
          };
          this.loadUsers();
          setTimeout(() => this.successMessage = '', 4000);
        } else {
          this.errorMessage = res.message || 'Failed to create user.';
        }
      },
      error: (err) => {
        this.submitting = false;
        this.errorMessage = err?.message || 'Error communicating with server.';
      }
    });
  }

  toggleUser(u: AdminUser): void {
    this.auth.toggleAdminUser(u).subscribe({
      next: () => {
        // Status updated
      },
      error: () => {
        u.status = u.status === 'Active' ? 'Inactive' : 'Active';
      }
    });
  }

  openEdit(u: AdminUser): void {
    this.editing = { ...u, password: '' };
    this.showEdit = true;
  }

  saveEdit(x: any): void {
    this.auth.saveAdminUser(x).subscribe({
      next: (res) => {
        if (res.success) {
          this.loadUsers();
          this.showEdit = false;
          this.editing = null;
        } else {
          alert(res.message || 'Failed to update user.');
        }
      },
      error: () => {
        const u = this.users.find(v => v.id === x.id);
        if (u) Object.assign(u, x);
        this.showEdit = false;
        this.editing = null;
      }
    });
  }

  closeEdit(): void {
    this.showEdit = false;
    this.editing = null;
  }

  deleteUser(u: AdminUser): void {
    if (u.id === 1) {
      alert('Primary Super Admin account cannot be deleted.');
      return;
    }
    if (confirm(`Are you sure you want to delete user "${u.username}" (${u.name})?`)) {
      this.auth.deleteAdminUser(u.id).subscribe({
        next: (res) => {
          if (res.success) {
            this.loadUsers();
          } else {
            alert(res.message || 'Could not delete user.');
          }
        },
        error: (err) => {
          alert(err?.message || 'Error deleting user.');
        }
      });
    }
  }

  label(p: string): string {
    return this.labels[p] || p;
  }
}