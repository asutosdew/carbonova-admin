import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

interface Product {
  id:number; name:string; price:number; dpPrice:number; rewardPoint:number; active:boolean;
}

@Component({
  selector:'app-products',
  standalone:true,
  imports:[CommonModule,FormsModule],
  templateUrl:'./products.component.html',
  styleUrl:'./products.component.scss'
})
export class ProductsComponent {
  products:Product[]=[
    {id:1,name:'Organic Farm Starter Kit',price:2500,dpPrice:2200,rewardPoint:25,active:true},
    {id:2,name:'Carbon Farming Growth Kit',price:5000,dpPrice:4500,rewardPoint:55,active:true},
    {id:3,name:'Bio Farming Support Kit',price:10000,dpPrice:9000,rewardPoint:120,active:false}
  ];

  showForm=false; showConfirm=false; editingProduct:Product|null=null;
  pendingAction:'save'|'toggle'|null=null; pendingProduct:Product|null=null;
  form:Product=this.emptyProduct(); search=''; statusFilter='All';

  emptyProduct():Product{return {id:0,name:'',price:0,dpPrice:0,rewardPoint:0,active:true}}
  get filteredProducts(){const q=this.search.trim().toLowerCase();return this.products.filter(p=>(!q||p.name.toLowerCase().includes(q)||String(p.id).includes(q))&&(this.statusFilter==='All'||(this.statusFilter==='Active'&&p.active)||(this.statusFilter==='Inactive'&&!p.active)))}
  get activeCount(){return this.products.filter(p=>p.active).length}
  get inactiveCount(){return this.products.filter(p=>!p.active).length}

  openAdd(){this.editingProduct=null;this.form=this.emptyProduct();this.showForm=true}
  openEdit(p:Product){this.editingProduct=p;this.form={...p};this.showForm=true}
  closeForm(){this.showForm=false;this.editingProduct=null;this.form=this.emptyProduct()}
  askSave(){if(!this.form.name.trim()||this.form.price<=0||this.form.dpPrice<0||this.form.dpPrice>this.form.price)return;this.pendingAction='save';this.pendingProduct={...this.form};this.showConfirm=true}
  askToggle(p:Product){this.pendingAction='toggle';this.pendingProduct=p;this.showConfirm=true}
  confirmAction(){
    if(!this.pendingProduct||!this.pendingAction)return;
    if(this.pendingAction==='save'){
      if(this.editingProduct){Object.assign(this.editingProduct,this.pendingProduct)}
      else this.products.unshift({...this.pendingProduct,id:this.products.length?Math.max(...this.products.map(x=>x.id))+1:1});
      this.closeForm();
    } else this.pendingProduct.active=!this.pendingProduct.active;
    this.closeConfirm();
  }
  closeConfirm(){this.showConfirm=false;this.pendingAction=null;this.pendingProduct=null}
  get confirmationTitle(){return this.pendingAction==='toggle'?(this.pendingProduct?.active?'Deactivate Product?':'Activate Product?'):(this.editingProduct?'Confirm Product Update':'Confirm New Product')}
  get confirmationMessage(){return this.pendingAction==='toggle'?(this.pendingProduct?.active?'This product will become inactive and unavailable for new selections.':'This product will become active and available for packages and farmer selections.'):(this.editingProduct?'Confirm the product details before saving changes.':'Confirm that you want to add this product to the catalogue.')}
  get confirmationButton(){return this.pendingAction==='toggle'?(this.pendingProduct?.active?'Deactivate':'Activate'):(this.editingProduct?'Save Changes':'Add Product')}
}
