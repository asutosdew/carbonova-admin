import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DataService } from '../../core/data.service';
import { PageHeaderComponent } from '../../shared/page-header.component';
import { PaginationComponent } from '../../shared/pagination/pagination.component';

@Component({
  selector: 'app-payments',
  standalone: true,
  imports: [CommonModule, PageHeaderComponent, PaginationComponent],
  templateUrl: './payments.component.html',
  styleUrl: './payments.component.scss'
})
export class PaymentsComponent {
  page = 1;
  pageSize = 10;

  get pagedPayments() {
    return this.d.payments.slice((this.page - 1) * this.pageSize, this.page * this.pageSize);
  }

  onPageChange(p: number) {
    this.page = p;
  }

  onPageSizeChange(size: number) {
    this.pageSize = size;
    this.page = 1;
  }

  constructor(public d: DataService) {}
}