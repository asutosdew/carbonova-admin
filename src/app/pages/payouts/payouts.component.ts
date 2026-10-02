import {Component} from '@angular/core';import {CommonModule} from '@angular/common';import {DataService} from '../../core/data.service';import {PageHeaderComponent} from '../../shared/page-header.component';import {PaginationComponent} from '../../shared/pagination/pagination.component';@Component({selector:'app-payouts',standalone:true,imports:[CommonModule,PageHeaderComponent,PaginationComponent],templateUrl:'./payouts.component.html',styleUrl:'./payouts.component.scss'})export class PayoutsComponent{page=1;pageSize=10;
  get pagedPayouts(){return this.d.payouts.slice((this.page-1)*this.pageSize,this.page*this.pageSize);}
  onPageChange(p:number){this.page=p;}
  onPageSizeChange(size:number){this.pageSize=size;this.page=1;}
  constructor(public d:DataService){} }