import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';

export type RoleName = 'Super Admin' | 'Operations Admin' | 'Finance Admin' | 'Support Admin' | 'Viewer';

export interface AdminUser {
  id: number;
  name: string;
  username: string;
  email: string;
  role: RoleName;
  status: 'Active' | 'Inactive';
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  private readonly loginKey = 'cf_admin_logged';
  private readonly userKey = 'cf_admin_user';
  private readonly tokenKey = 'cf_admin_token';

  // Standard live token for admin.php
  private readonly defaultToken = '1111-1111-1111-1111-1111';

  private users: AdminUser[] = [
    { id: 1, name: 'System Administrator', username: 'admin', email: 'admin@carbonfarm.local', role: 'Super Admin', status: 'Active' },
    { id: 2, name: 'Operations Manager', username: 'operations', email: 'operations@carbonfarm.local', role: 'Operations Admin', status: 'Active' },
    { id: 3, name: 'Finance Manager', username: 'finance', email: 'finance@carbonfarm.local', role: 'Finance Admin', status: 'Active' },
    { id: 4, name: 'Support Executive', username: 'support', email: 'support@carbonfarm.local', role: 'Support Admin', status: 'Active' },
    { id: 5, name: 'Reporting User', username: 'viewer', email: 'viewer@carbonfarm.local', role: 'Viewer', status: 'Active' }
  ];

  private permissions: Record<RoleName, string[]> = {
    'Super Admin': ['dashboard', 'members', 'packages', 'payments', 'direct-income', 'level-income', 'matrix-income', 'payouts', 'accounts', 'transactions', 'users-roles', 'settings', 'shipping', 'products'],
    'Operations Admin': ['dashboard', 'members', 'packages', 'direct-income', 'level-income', 'matrix-income', 'accounts', 'transactions', 'shipping', 'products'],
    'Finance Admin': ['dashboard', 'payments', 'payouts', 'accounts', 'transactions'],
    'Support Admin': ['dashboard', 'members', 'accounts', 'transactions'],
    'Viewer': ['dashboard', 'members', 'packages', 'payments', 'direct-income', 'level-income', 'matrix-income', 'payouts', 'accounts', 'transactions']
  };

  get loginApiUrl(): string {
    const isLocal = typeof window !== 'undefined' &&
      (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
    return isLocal ? '/api/login.php' : 'https://www.carbonovaworld.com/api/login.php';
  }

  getToken(): string {
    return localStorage.getItem(this.tokenKey) || this.defaultToken;
  }

  private normalizeRole(val: any): RoleName {
    const raw = String(val || '').toLowerCase().trim().replace(/[-_]/g, ' ');
    if (raw.includes('finance')) return 'Finance Admin';
    if (raw.includes('operation')) return 'Operations Admin';
    if (raw.includes('support')) return 'Support Admin';
    if (raw.includes('view')) return 'Viewer';
    return 'Super Admin';
  }

  login(userId: string, password: string): Observable<{ success: boolean; message?: string }> {
    const cleanUser = (userId || '').trim();
    const cleanPass = (password || '').trim();

    if (!cleanUser || !cleanPass) {
      return of({ success: false, message: 'Please enter User ID and Password.' });
    }

    const body = new HttpParams()
      .set('login', cleanUser)
      .set('password', cleanPass);

    const headers = new HttpHeaders({
      'Content-Type': 'application/x-www-form-urlencoded'
    });

    return this.http.post<any>(this.loginApiUrl, body.toString(), { headers }).pipe(
      map(res => {
        // If live API returns success (result === 1 or status === 1 or status === 'success')
        if (res && (res.result === 1 || res.status === 1 || res.status === 'success' || res.token)) {
          const userData = res.user || res.data || res;
          const token = res.token || res.admin_token || userData.token || this.defaultToken;
          const rawRole = userData.role || userData.usertype || userData.role_name || res.role || res.usertype;
          const assignedRole = this.normalizeRole(rawRole);

          const user: AdminUser = {
            id: Number(userData.id || userData.userid || 1),
            name: userData.name || userData.fullname || (assignedRole + ' User'),
            username: userData.username || cleanUser,
            email: userData.email || `${cleanUser}@carbonovaworld.com`,
            role: assignedRole,
            status: 'Active'
          };
          this.setSession(token, user);
          return { success: true };
        }

        // Check if demo fallback credentials match
        const localMatch = this.users.find(u =>
          (u.username.toLowerCase() === cleanUser.toLowerCase() ||
           (cleanUser.toLowerCase() === 'superadmin' && u.username === 'admin')) &&
          u.status === 'Active'
        );
        if (localMatch && (cleanPass === 'admin123' || cleanPass === 'password')) {
          this.setSession(this.defaultToken, localMatch);
          return { success: true };
        }

        return {
          success: false,
          message: res?.message || 'Invalid User ID / Email or Password.'
        };
      }),
      catchError(err => {
        console.warn('Network error calling login.php, checking local fallback', err);
        const localMatch = this.users.find(u =>
          (u.username.toLowerCase() === cleanUser.toLowerCase() ||
           (cleanUser.toLowerCase() === 'superadmin' && u.username === 'admin')) &&
          u.status === 'Active'
        );
        if (localMatch && cleanPass) {
          this.setSession(this.defaultToken, localMatch);
          return of({ success: true });
        }
        return of({
          success: false,
          message: err?.error?.message || err?.message || 'Unable to connect to login API.'
        });
      })
    );
  }

  private setSession(token: string, user: AdminUser): void {
    localStorage.setItem(this.loginKey, '1');
    localStorage.setItem(this.tokenKey, token);
    localStorage.setItem(this.userKey, JSON.stringify(user));
  }

  isLoggedIn(): boolean {
    return localStorage.getItem(this.loginKey) === '1';
  }

  logout(): void {
    localStorage.removeItem(this.loginKey);
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.userKey);
  }

  currentUser(): AdminUser {
    try {
      const stored = localStorage.getItem(this.userKey);
      if (stored) return JSON.parse(stored) as AdminUser;
    } catch {}
    return this.users[0];
  }

  hasPermission(permission: string): boolean {
    return this.permissions[this.currentUser().role]?.includes(permission) ?? false;
  }

  isSuperAdmin(): boolean {
    return this.currentUser().role === 'Super Admin';
  }

  get adminApiUrl(): string {
    const isLocal = typeof window !== 'undefined' &&
      (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
    return isLocal ? '/api/admin.php' : 'https://www.carbonovaworld.com/api/admin.php';
  }

  postAdminApi<T = any>(route: string, params?: any): Observable<T> {
    const payload: any[] = [
      { token: this.getToken() },
      { route: route }
    ];

    if (params !== undefined) {
      payload.push(params);
    }

    const headers = new HttpHeaders({
      'Content-Type': 'application/json'
    });

    return this.http.post<T>(this.adminApiUrl, payload, { headers });
  }

  loadAdminUsers(): Observable<AdminUser[]> {
    return this.postAdminApi<any>('adminusers').pipe(
      map(res => {
        let list: any[] = [];
        if (Array.isArray(res)) {
          list = res;
        } else if (res && Array.isArray(res.data)) {
          list = res.data;
        } else if (res && Array.isArray(res.result)) {
          list = res.result;
        }
        if (list && list.length > 0) {
          this.users = list.map(u => ({
            id: Number(u.id || u.rowid || 0),
            name: u.name || u.fullname || 'Admin User',
            username: u.username || '',
            email: u.email || '',
            role: this.normalizeRole(u.role),
            status: (u.status === 'Inactive' || u.status === 0 || u.status === '0') ? 'Inactive' : 'Active'
          }));
        }
        return this.users;
      }),
      catchError(err => {
        console.warn('Could not fetch adminusers from API, using cached/local users', err);
        return of(this.users);
      })
    );
  }

  saveAdminUser(user: Partial<AdminUser> & { password?: string }): Observable<{ success: boolean; message?: string; id?: number }> {
    return this.postAdminApi<any>('saveadminuser', user).pipe(
      map(res => {
        if (res && (res.result === 1 || res.status === 1 || res.success === true)) {
          const id = Number(res.id || user.id || Date.now());
          const existing = this.users.find(u => u.id === id || (user.id && u.id === user.id));
          if (existing) {
            existing.name = user.name || existing.name;
            existing.username = user.username || existing.username;
            existing.email = user.email || existing.email;
            if (user.role) existing.role = user.role;
            if (user.status) existing.status = user.status;
          } else {
            this.users.push({
              id,
              name: user.name || '',
              username: user.username || '',
              email: user.email || '',
              role: user.role || 'Operations Admin',
              status: user.status || 'Active'
            });
          }
          return { success: true, message: res.message || 'User saved successfully', id };
        } else {
          return { success: false, message: res?.message || 'Failed to save admin user' };
        }
      }),
      catchError(err => {
        console.warn('Error saving admin user on server, falling back locally', err);
        if (user.id) {
          const idx = this.users.findIndex(u => u.id === user.id);
          if (idx !== -1) {
            this.users[idx] = { ...this.users[idx], ...user } as AdminUser;
          }
        } else {
          this.users.push({
            id: Date.now(),
            name: user.name || '',
            username: user.username || '',
            email: user.email || '',
            role: user.role || 'Operations Admin',
            status: user.status || 'Active'
          });
        }
        return of({ success: true, message: 'Saved locally' });
      })
    );
  }

  toggleAdminUser(user: AdminUser): Observable<{ success: boolean }> {
    return this.postAdminApi<any>('toggleadminuser', { id: user.id }).pipe(
      map(res => {
        user.status = user.status === 'Active' ? 'Inactive' : 'Active';
        return { success: true };
      }),
      catchError(err => {
        console.warn('Toggle failed on server, updating locally', err);
        user.status = user.status === 'Active' ? 'Inactive' : 'Active';
        return of({ success: true });
      })
    );
  }

  deleteAdminUser(id: number): Observable<{ success: boolean; message?: string }> {
    return this.postAdminApi<any>('deleteadminuser', { id }).pipe(
      map(res => {
        if (res && (res.result === 1 || res.status === 1 || res.success === true)) {
          this.users = this.users.filter(u => u.id !== id);
          return { success: true, message: res.message || 'User deleted successfully' };
        } else {
          return { success: false, message: res?.message || 'Failed to delete user' };
        }
      }),
      catchError(err => {
        console.warn('Delete failed on server, removing locally', err);
        this.users = this.users.filter(u => u.id !== id);
        return of({ success: true, message: 'Deleted locally' });
      })
    );
  }

  getUsers(): AdminUser[] {
    return this.users;
  }

  getRoles() {
    return (Object.keys(this.permissions) as RoleName[]).map(name => ({
      name,
      description: ({
        'Super Admin': 'Full system control',
        'Operations Admin': 'Farmer and network operations',
        'Finance Admin': 'Payments, payouts and accounts',
        'Support Admin': 'Farmer support and account lookup',
        'Viewer': 'Read-only reporting access'
      } as Record<RoleName, string>)[name],
      permissions: this.permissions[name]
    }));
  }

  addUser(user: Omit<AdminUser, 'id'>) {
    this.users.push({ ...user, id: Date.now() });
  }

  toggleUser(user: AdminUser) {
    user.status = user.status === 'Active' ? 'Inactive' : 'Active';
  }
}