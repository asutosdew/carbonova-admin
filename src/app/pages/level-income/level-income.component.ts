import {Component} from '@angular/core';import {CommonModule} from '@angular/common';import {DataService} from '../../core/data.service';import {PageHeaderComponent} from '../../shared/page-header.component';import {PaginationComponent} from '../../shared/pagination/pagination.component';@Component({selector:'app-level-income',standalone:true,imports:[CommonModule,PageHeaderComponent,PaginationComponent],templateUrl:'./level-income.component.html',styleUrl:'./level-income.component.scss'})export class LevelIncomeComponent{page=1;pageSize=10;
  get pagedLevel(){return this.d.level.slice((this.page-1)*this.pageSize,this.page*this.pageSize);}
  onPageChange(p:number){this.page=p;}
  onPageSizeChange(size:number){this.pageSize=size;this.page=1;}
  constructor(public d:DataService){}}