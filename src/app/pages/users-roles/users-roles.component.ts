import {Component} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FormsModule} from '@angular/forms';
import {AuthService,AdminUser,RoleName} from '../../core/auth.service';
import {PageHeaderComponent} from '../../shared/page-header.component';import {EditModalComponent} from '../../shared/edit-modal/edit-modal.component';import {PaginationComponent} from '../../shared/pagination/pagination.component';

@Component({selector:'app-users-roles',standalone:true,imports:[CommonModule,FormsModule,PageHeaderComponent,PaginationComponent,EditModalComponent],templateUrl:'./users-roles.component.html',styleUrl:'./users-roles.component.scss'})
export class UsersRolesComponent{
  tab:'users'|'roles'='users';showForm=false;page=1;pageSize=10;get pagedUsers(){return this.users.slice((this.page-1)*this.pageSize,this.page*this.pageSize);}onPageChange(p:number){this.page=p;}onPageSizeChange(size:number){this.pageSize=size;this.page=1;}users:AdminUser[];roles:any[];
  newUser={name:'',username:'',email:'',role:'Operations Admin' as RoleName,status:'Active' as 'Active'};
  labels:Record<string,string>={dashboard:'Dashboard',members:'Members',packages:'Packages',payments:'Payments','direct-income':'Direct Income','level-income':'Level Income','matrix-income':'Matrix Income',payouts:'Payouts',accounts:'Farmer Accounts',transactions:'Transactions','users-roles':'Users & Roles',settings:'Settings'};
  constructor(private auth:AuthService){this.users=auth.getUsers();this.roles=auth.getRoles();}
  addUser(){if(!this.newUser.name.trim()||!this.newUser.username.trim())return;this.auth.addUser(this.newUser);this.users=this.auth.getUsers();this.page=1;this.showForm=false;this.newUser={name:'',username:'',email:'',role:'Operations Admin',status:'Active'};}
  toggleUser(u:AdminUser){this.auth.toggleUser(u);}
  showEdit=false;editing:any=null;openEdit(u:any){this.editing={...u};this.showEdit=true;}saveEdit(x:any){const u=this.users.find(v=>v.id===x.id);if(u)Object.assign(u,x);this.showEdit=false;this.editing=null;}closeEdit(){this.showEdit=false;this.editing=null;}
  label(p:string){return this.labels[p]||p;}
}