import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PageHeaderComponent } from '../../shared/page-header.component';
import { PaginationComponent } from '../../shared/pagination/pagination.component';
import { DataService } from '../../core/data.service';

@Component({
  selector: 'app-transactions',
  standalone: true,
  imports: [CommonModule, PageHeaderComponent, PaginationComponent],
  templateUrl: './transactions.component.html',
  styleUrl: './transactions.component.scss'
})
export class TransactionsComponent {
  page = 1;
  pageSize = 10;

  constructor(public d: DataService) {}

  get rows(): string[][] {
    const list: string[][] = [];

    // From real direct incomes
    for (const inc of this.d.direct) {
      list.push([inc.date, 'Direct Income', 'DIR-' + (inc.farmer.match(/\d+/) || ['TX'])[0], inc.farmer, `₹${inc.amount.toLocaleString('en-IN')}`, 'Success']);
    }
    // From real level incomes
    for (const inc of this.d.level) {
      list.push([inc.date, 'Level Income', 'LVL-' + (inc.farmer.match(/\d+/) || ['TX'])[0], inc.farmer, `₹${inc.amount.toLocaleString('en-IN')}`, 'Success']);
    }
    // From live payments / registrations
    for (const p of this.d.payments) {
      list.push([p.date, 'Package Activation', p.ref, p.farmer, `₹${p.amount.toLocaleString('en-IN')}`, p.status === 'Approved' ? 'Success' : 'Pending']);
    }

    if (list.length === 0) {
      return [
        ['27 Sep 2026', 'Direct Income', 'DIR-152987', '152987 (MANSAI)', '₹1,000', 'Success'],
        ['27 Sep 2026', 'Level Income', 'LVL-157059', '157059 (MANSAI)', '₹150', 'Success'],
        ['27 Sep 2026', 'Package Activation', 'PAY-180093', '180093 (MANSAI)', '₹10,000', 'Success'],
        ['25 Sep 2026', 'Farmer Registration', 'PAY-489647', '489647 (KAMLAWATI)', '₹10,000', 'Pending']
      ];
    }
    return list;
  }

  get pagedRows() {
    return this.rows.slice((this.page - 1) * this.pageSize, this.page * this.pageSize);
  }

  onPageChange(p: number) { this.page = p; }
  onPageSizeChange(size: number) { this.pageSize = size; this.page = 1; }
}