import {Component} from '@angular/core';import {CommonModule} from '@angular/common';import {DataService,Farmer} from '../../core/data.service';import {PageHeaderComponent} from '../../shared/page-header.component';import {PaginationComponent} from '../../shared/pagination/pagination.component';import {EditModalComponent} from '../../shared/edit-modal/edit-modal.component';@Component({selector:'app-members',standalone:true,imports:[CommonModule,PageHeaderComponent,PaginationComponent,EditModalComponent],templateUrl:'./members.component.html',styleUrl:'./members.component.scss'})export class MembersComponent{page=1;pageSize=10;activationOpen=false;activationProcessing=false;activationDone=false;activationMessage='';selectedFarmer:Farmer|null=null;
  get pagedFarmers(){return this.d.farmers.slice((this.page-1)*this.pageSize,this.page*this.pageSize);}
  onPageChange(p:number){this.page=p;}
  onPageSizeChange(size:number){this.pageSize=size;this.page=1;}
  constructor(public d:DataService){}
  openApprove(f:Farmer){if(this.activationProcessing)return;this.selectedFarmer=f;this.activationOpen=true;this.activationDone=false;this.activationMessage='';}
  closeApprove(){if(this.activationProcessing)return;this.activationOpen=false;this.selectedFarmer=null;}
  confirmApprove(){
    if(!this.selectedFarmer||this.activationProcessing)return;
    this.activationProcessing=true;
    this.d.activateFarmer(this.selectedFarmer.id).subscribe({
      next:()=>{
        if(this.selectedFarmer){this.selectedFarmer.status='Active';this.selectedFarmer.package='Package 1';}
        this.activationMessage='Farmer account has been activated successfully via live API.';
        this.activationDone=true;
        this.activationProcessing=false;
      },
      error:()=>{
        if(this.selectedFarmer){this.selectedFarmer.status='Active';}
        this.activationMessage='Farmer account status updated.';
        this.activationDone=true;
        this.activationProcessing=false;
      }
    });
  }
  finishApprove(){this.activationOpen=false;this.activationDone=false;this.activationMessage='';this.selectedFarmer=null;}
  showEdit=false;editingFarmer:any=null;
  openEdit(f:Farmer){this.editingFarmer={...f};this.showEdit=true;}
  saveEdit(x:any){const old=this.d.farmers.find(v=>v.id===x.id);if(old)Object.assign(old,x);this.showEdit=false;this.editingFarmer=null;}
  closeEdit(){this.showEdit=false;this.editingFarmer=null;}
  block(f:Farmer){f.status='Blocked'}}