import {Component} from '@angular/core';import {CommonModule} from '@angular/common';import {DataService} from '../../core/data.service';import {PageHeaderComponent} from '../../shared/page-header.component';import {PaginationComponent} from '../../shared/pagination/pagination.component';@Component({selector:'app-accounts',standalone:true,imports:[CommonModule,PageHeaderComponent,PaginationComponent],templateUrl:'./accounts.component.html',styleUrl:'./accounts.component.scss'})export class AccountsComponent{page=1;pageSize=10;
  get pagedFarmers(){return this.d.farmers.slice((this.page-1)*this.pageSize,this.page*this.pageSize);}
  onPageChange(p:number){this.page=p;}
  onPageSizeChange(size:number){this.pageSize=size;this.page=1;}
  constructor(public d:DataService){}}