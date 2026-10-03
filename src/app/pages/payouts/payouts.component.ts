import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService, Income } from '../../core/data.service';
import { PageHeaderComponent } from '../../shared/page-header.component';
import { PaginationComponent } from '../../shared/pagination/pagination.component';

export interface DaywisePayoutItem {
  date: string;
  rawDate: string;
  userId: string;
  userName: string;
  directIncome: number;
  levelIncome: number;
  matrixIncome: number;
  totalPayout: number;
  status: 'Credited' | 'Pending';
}

export interface ConsolidatedDailyItem {
  date: string;
  rawDate: string;
  totalFarmers: number;
  directIncome: number;
  levelIncome: number;
  matrixIncome: number;
  totalPayout: number;
}

@Component({
  selector: 'app-payouts',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, PaginationComponent],
  templateUrl: './payouts.component.html',
  styleUrl: './payouts.component.scss'
})
export class PayoutsComponent {
  page = 1;
  pageSize = 10;

  selectedUser = '';
  fromDate = '';
  toDate = '';
  searchTerm = '';
  activePreset: 'all' | 'today' | '7days' | 'month' = 'all';
  viewMode: 'user_daywise' | 'daily_consolidated' = 'user_daywise';

  constructor(public d: DataService) {}

  // List of farmers for the user-wise dropdown
  get userList(): { id: string; name: string }[] {
    const map = new Map<string, string>();
    if (this.d.farmers && this.d.farmers.length > 0) {
      for (const f of this.d.farmers) {
        if (f.id) map.set(f.id, f.name || `Farmer ${f.id}`);
      }
    }
    // Also include users present in direct/level/matrix
    const all = [...this.d.direct, ...this.d.level, ...this.d.matrix];
    for (const item of all) {
      const uid = item.userId || this.parseUserId(item.farmer);
      const uname = item.userName || this.parseUserName(item.farmer);
      if (uid && !map.has(uid)) {
        map.set(uid, uname || `Farmer ${uid}`);
      }
    }
    return Array.from(map.entries()).map(([id, name]) => ({ id, name }));
  }

  // Raw combined and grouped daywise records
  get allDaywiseRecords(): DaywisePayoutItem[] {
    const map = new Map<string, DaywisePayoutItem>();

    const process = (item: Income, type: 'direct' | 'level' | 'matrix') => {
      const rawDate = this.normalizeRawDate(item.rawDate || item.date);
      const dateDisplay = this.formatDateDisplay(rawDate, item.date);
      const userId = item.userId || this.parseUserId(item.farmer) || '180093';
      const userName = item.userName || this.parseUserName(item.farmer) || 'Farmer ' + userId;
      const key = `${rawDate}_${userId}`;

      if (!map.has(key)) {
        map.set(key, {
          date: dateDisplay,
          rawDate: rawDate,
          userId: userId,
          userName: userName,
          directIncome: 0,
          levelIncome: 0,
          matrixIncome: 0,
          totalPayout: 0,
          status: 'Credited'
        });
      }

      const row = map.get(key)!;
      const amt = Number(item.amount) || 0;
      if (type === 'direct') row.directIncome += amt;
      if (type === 'level') row.levelIncome += amt;
      if (type === 'matrix') row.matrixIncome += amt;
      row.totalPayout = row.directIncome + row.levelIncome + row.matrixIncome;
      if (item.status === 'Pending') row.status = 'Pending';
    };

    this.d.direct.forEach(x => process(x, 'direct'));
    this.d.level.forEach(x => process(x, 'level'));
    this.d.matrix.forEach(x => process(x, 'matrix'));

    return Array.from(map.values()).sort((a, b) => b.rawDate.localeCompare(a.rawDate));
  }

  // Filtered User Daywise Items
  get filteredUserDaywise(): DaywisePayoutItem[] {
    const q = this.searchTerm.trim().toLowerCase();
    return this.allDaywiseRecords.filter(row => {
      // User Filter
      if (this.selectedUser && row.userId !== this.selectedUser) {
        return false;
      }
      // Date Range Filter
      if (this.fromDate && row.rawDate < this.fromDate) {
        return false;
      }
      if (this.toDate && row.rawDate > this.toDate) {
        return false;
      }
      // Text Search
      if (q) {
        const matches = row.userId.toLowerCase().includes(q) ||
          row.userName.toLowerCase().includes(q) ||
          row.date.toLowerCase().includes(q);
        if (!matches) return false;
      }
      return true;
    });
  }

  // Daily Consolidated Items (Date wise sum across all users)
  get filteredDailyConsolidated(): ConsolidatedDailyItem[] {
    const map = new Map<string, {
      date: string;
      rawDate: string;
      users: Set<string>;
      direct: number;
      level: number;
      matrix: number;
      total: number;
    }>();

    for (const row of this.filteredUserDaywise) {
      if (!map.has(row.rawDate)) {
        map.set(row.rawDate, {
          date: row.date,
          rawDate: row.rawDate,
          users: new Set(),
          direct: 0,
          level: 0,
          matrix: 0,
          total: 0
        });
      }
      const entry = map.get(row.rawDate)!;
      entry.users.add(row.userId);
      entry.direct += row.directIncome;
      entry.level += row.levelIncome;
      entry.matrix += row.matrixIncome;
      entry.total += row.totalPayout;
    }

    return Array.from(map.values())
      .map(e => ({
        date: e.date,
        rawDate: e.rawDate,
        totalFarmers: e.users.size,
        directIncome: e.direct,
        levelIncome: e.level,
        matrixIncome: e.matrix,
        totalPayout: e.total
      }))
      .sort((a, b) => b.rawDate.localeCompare(a.rawDate));
  }

  // Paged items based on current viewMode
  get pagedItems(): DaywisePayoutItem[] {
    const start = (this.page - 1) * this.pageSize;
    return this.filteredUserDaywise.slice(start, start + this.pageSize);
  }

  get pagedDailyConsolidated(): ConsolidatedDailyItem[] {
    const start = (this.page - 1) * this.pageSize;
    return this.filteredDailyConsolidated.slice(start, start + this.pageSize);
  }

  get totalItemsCount(): number {
    return this.viewMode === 'user_daywise'
      ? this.filteredUserDaywise.length
      : this.filteredDailyConsolidated.length;
  }

  // Summary KPI Totals
  get totalPayoutSum(): number {
    return this.filteredUserDaywise.reduce((acc, r) => acc + r.totalPayout, 0);
  }

  get totalDirectSum(): number {
    return this.filteredUserDaywise.reduce((acc, r) => acc + r.directIncome, 0);
  }

  get totalLevelSum(): number {
    return this.filteredUserDaywise.reduce((acc, r) => acc + r.levelIncome, 0);
  }

  get totalMatrixSum(): number {
    return this.filteredUserDaywise.reduce((acc, r) => acc + r.matrixIncome, 0);
  }

  // Date Presets
  setPreset(preset: 'all' | 'today' | '7days' | 'month'): void {
    this.activePreset = preset;
    this.page = 1;
    const now = new Date();
    const formatYMD = (d: Date) => {
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };

    if (preset === 'all') {
      this.fromDate = '';
      this.toDate = '';
    } else if (preset === 'today') {
      const todayStr = formatYMD(now);
      this.fromDate = todayStr;
      this.toDate = todayStr;
    } else if (preset === '7days') {
      const past = new Date();
      past.setDate(now.getDate() - 7);
      this.fromDate = formatYMD(past);
      this.toDate = formatYMD(now);
    } else if (preset === 'month') {
      const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
      this.fromDate = formatYMD(firstDay);
      this.toDate = formatYMD(now);
    }
  }

  onUserChange(): void {
    this.page = 1;
  }

  onDateChange(): void {
    this.activePreset = 'all';
    this.page = 1;
  }

  onPageChange(p: number): void {
    this.page = p;
  }

  onPageSizeChange(size: number): void {
    this.pageSize = size;
    this.page = 1;
  }

  resetFilters(): void {
    this.selectedUser = '';
    this.fromDate = '';
    this.toDate = '';
    this.searchTerm = '';
    this.activePreset = 'all';
    this.page = 1;
  }

  // Export to CSV
  exportCSV(): void {
    let csv = '';
    if (this.viewMode === 'user_daywise') {
      csv = 'Date,User ID,Farmer Name,Direct Income (INR),Level Income (INR),Matrix Income (INR),Total Payout (INR),Status\r\n';
      for (const row of this.filteredUserDaywise) {
        const line = [
          `"${row.date}"`,
          `"${row.userId}"`,
          `"${row.userName.replace(/"/g, '""')}"`,
          row.directIncome.toFixed(2),
          row.levelIncome.toFixed(2),
          row.matrixIncome.toFixed(2),
          row.totalPayout.toFixed(2),
          `"${row.status}"`
        ].join(',');
        csv += line + '\r\n';
      }
      csv += `\r\n"TOTAL","","","${this.totalDirectSum.toFixed(2)}","${this.totalLevelSum.toFixed(2)}","${this.totalMatrixSum.toFixed(2)}","${this.totalPayoutSum.toFixed(2)}",""\r\n`;
    } else {
      csv = 'Date,Total Active Farmers,Direct Income (INR),Level Income (INR),Matrix Income (INR),Total Payout (INR)\r\n';
      for (const row of this.filteredDailyConsolidated) {
        const line = [
          `"${row.date}"`,
          row.totalFarmers,
          row.directIncome.toFixed(2),
          row.levelIncome.toFixed(2),
          row.matrixIncome.toFixed(2),
          row.totalPayout.toFixed(2)
        ].join(',');
        csv += line + '\r\n';
      }
      csv += `\r\n"TOTAL","","${this.totalDirectSum.toFixed(2)}","${this.totalLevelSum.toFixed(2)}","${this.totalMatrixSum.toFixed(2)}","${this.totalPayoutSum.toFixed(2)}"\r\n`;
    }

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    const dateTag = new Date().toISOString().slice(0, 10);
    link.download = `Payout_Summary_${this.viewMode}_${dateTag}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  private normalizeRawDate(val: any): string {
    if (!val) return '2026-10-02';
    try {
      const d = new Date(val);
      if (!isNaN(d.getTime())) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
      }
    } catch {}
    return String(val).slice(0, 10);
  }

  private formatDateDisplay(rawYmd: string, fallback: string): string {
    try {
      const parts = rawYmd.split('-');
      if (parts.length === 3) {
        const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
      }
    } catch {}
    return fallback || rawYmd;
  }

  private parseUserId(farmerStr: string): string {
    if (!farmerStr) return '';
    const m = farmerStr.match(/\b\d{4,}\b/);
    return m ? m[0] : farmerStr.split(' ')[0] || '';
  }

  private parseUserName(farmerStr: string): string {
    if (!farmerStr) return '';
    const m = farmerStr.match(/\((.*?)\)/);
    if (m && m[1]) return m[1].trim();
    return farmerStr;
  }
}