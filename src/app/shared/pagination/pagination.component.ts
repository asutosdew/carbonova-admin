import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-pagination',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './pagination.component.html',
  styleUrl: './pagination.component.scss'
})
export class PaginationComponent {
  @Input() page = 1;
  @Input() pageSize = 10;
  @Input() total = 0;
  @Input() pageSizes: number[] = [10, 25, 50];
  @Output() pageChange = new EventEmitter<number>();
  @Output() pageSizeChange = new EventEmitter<number>();

  get totalPages(): number { return Math.max(1, Math.ceil(this.total / this.pageSize)); }
  get start(): number { return this.total ? (this.page - 1) * this.pageSize + 1 : 0; }
  get end(): number { return Math.min(this.page * this.pageSize, this.total); }

  get pages(): number[] {
    const total = this.totalPages;
    const current = this.page;
    if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
    const set = new Set<number>([1, total, current, current - 1, current + 1]);
    if (current <= 3) [2,3,4].forEach(x => set.add(x));
    if (current >= total - 2) [total-3,total-2,total-1].forEach(x => set.add(x));
    return [...set].filter(x => x >= 1 && x <= total).sort((a,b)=>a-b);
  }

  go(page: number) {
    const p = Math.max(1, Math.min(this.totalPages, page));
    if (p !== this.page) this.pageChange.emit(p);
  }

  changeSize(event: Event) {
    const value = Number((event.target as HTMLSelectElement).value);
    this.pageSizeChange.emit(value);
  }
}
