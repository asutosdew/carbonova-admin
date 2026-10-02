import {Component} from '@angular/core';import {CommonModule} from '@angular/common';import {FormsModule} from '@angular/forms';import {PageHeaderComponent} from '../../shared/page-header.component';import {PaginationComponent} from '../../shared/pagination/pagination.component';import {EditModalComponent} from '../../shared/edit-modal/edit-modal.component';
interface Shipment{ id:number; farmer:string; userId:string; mobile:string; product:string; qty:number; address:string; city:string; state:string; pincode:string; status:'Pending'|'Packed'|'Shipped'|'Delivered'|'On Hold'; tracking:string; shippingMode?:string; products?:any[]; }
@Component({selector:'app-shipping',standalone:true,imports:[CommonModule,FormsModule,PageHeaderComponent,PaginationComponent,EditModalComponent],templateUrl:'./shipping.component.html',styleUrl:'./shipping.component.scss'})
export class ShippingComponent{
 tab:'pending'|'all'='pending'; page=1; pageSize=10; status='All'; showEdit=false; editing:any=null;
 shipments:Shipment[]=[
 {id:1001,farmer:'Rakesh Patel',userId:'CF10421',mobile:'98XXXX1201',product:'Growth Product Kit',qty:1,address:'Main Road',city:'Raipur',state:'Chhattisgarh',pincode:'492001',status:'Pending',tracking:''},
 {id:1002,farmer:'Meena Sahu',userId:'CF10318',mobile:'97XXXX4408',product:'Professional Product Kit',qty:2,address:'Station Road',city:'Bilaspur',state:'Chhattisgarh',pincode:'495001',status:'Shipped',tracking:'CFD1002345'},
 {id:1003,farmer:'Amit Kumar',userId:'CF09812',mobile:'90XXXX3344',product:'Starter Product Kit',qty:1,address:'Market Road',city:'Raigarh',state:'Chhattisgarh',pincode:'496001',status:'Pending',tracking:''}];
 get base(){return this.tab==='pending'?this.shipments.filter(x=>x.status==='Pending'):this.shipments} get filtered(){let a=this.status==='All'?this.base:this.base.filter(x=>x.status===this.status);return a.slice((this.page-1)*this.pageSize,this.page*this.pageSize)} get total(){return (this.status==='All'?this.base:this.base.filter(x=>x.status===this.status)).length} get pendingCount(){return this.shipments.filter(x=>x.status==='Pending').length} get shippedCount(){return this.shipments.filter(x=>x.status==='Shipped').length} get deliveredCount(){return this.shipments.filter(x=>x.status==='Delivered').length}
 onPageChange(p:number){this.page=p} onPageSizeChange(s:number){this.pageSize=s;this.page=1} changeTab(t:'pending'|'all'){this.tab=t;this.page=1}
 openEdit(x:Shipment){this.editing={...x};this.showEdit=true} saveEdit(x:any){const old=this.shipments.find(v=>v.id===x.id);if(old)Object.assign(old,x);this.showEdit=false;this.editing=null} closeEdit(){this.showEdit=false;this.editing=null}

  showProducts = false;
  showShippingMode = false;
  showProductEdit = false;
  selectedShipmentProducts: any[] = [];
  editingProduct: any = null;
  shippingMode = 'Courier';
  shippingModes = ['Courier', 'India Post', 'Transport', 'Local Delivery', 'Self Pickup'];

  openShipmentProducts(s: Shipment): void {
    this.editing = s;
    this.selectedShipmentProducts = (s as any).products || [{
      id: 1, name: s.product, sku: 'CF-' + s.id,
      qty: s.qty, unitPrice: 0, total: 0
    }];
    this.showProducts = true;
  }

  closeShipmentProducts(): void {
    this.showProducts = false;
    this.selectedShipmentProducts = [];
  }

  openShippingMode(s: Shipment): void {
    this.editing = s;
    this.shippingMode = (s as any).shippingMode || 'Courier';
    this.showShippingMode = true;
  }

  saveShippingMode(): void {
    if (this.editing) (this.editing as any).shippingMode = this.shippingMode;
    this.showShippingMode = false;
  }

  openProductEdit(product: any): void {
    this.editingProduct = { ...product };
    this.showProductEdit = true;
  }

  saveProductEdit(): void {
    if (!this.editingProduct) return;
    const p = this.selectedShipmentProducts.find(x => x.id === this.editingProduct.id);
    if (p) Object.assign(p, this.editingProduct);
    if (this.editing) {
      (this.editing as any).products = [...this.selectedShipmentProducts];
      this.editing.qty = this.selectedShipmentProducts.reduce((n,p) => n + Number(p.qty || 0), 0);
    }
    this.showProductEdit = false;
    this.editingProduct = null;
  }


  printShippingLabel(s: Shipment): void {
    const products = (s as any).products || [{
      name: s.product,
      qty: s.qty
    }];

    const productRows = products.map((p: any) =>
      `<tr><td>${this.escapePrint(p.name)}</td><td>${Number(p.qty || 0)}</td></tr>`
    ).join('');

    this.openPrintWindow(
      `Shipping Label - ${s.userId}`,
      `
      <div class="label">
        <div class="brand">CARBONOVA</div>
        <div class="subtitle">FARMER PRODUCT DELIVERY</div>
        <div class="label-divider"></div>

        <div class="section-title">SHIP TO</div>
        <div class="recipient">${this.escapePrint(s.farmer)}</div>
        <div>${this.escapePrint(s.mobile)}</div>
        <div>${this.escapePrint(s.address)}</div>
        <div>${this.escapePrint(s.city)}, ${this.escapePrint(s.state)}</div>
        <div class="pincode">${this.escapePrint(s.pincode)}</div>

        <div class="label-divider"></div>

        <div class="meta">
          <div><span>Farmer ID</span><b>${this.escapePrint(s.userId)}</b></div>
          <div><span>Shipment ID</span><b>SHIP-${s.id}</b></div>
          <div><span>Mode</span><b>${this.escapePrint((s as any).shippingMode || 'Not Set')}</b></div>
          <div><span>Tracking</span><b>${this.escapePrint(s.tracking || 'Not Assigned')}</b></div>
        </div>

        <div class="label-divider"></div>

        <div class="section-title">PACKAGE CONTENT</div>
        <table><thead><tr><th>Product</th><th>Qty</th></tr></thead>
        <tbody>${productRows}</tbody></table>

        <div class="footer-note">Handle with care • Carbonova Farmer Fulfilment</div>
      </div>
      `
    );
  }

  printInvoice(s: Shipment): void {
    const products = (s as any).products || [{
      name: s.product,
      qty: s.qty,
      unitPrice: 0,
      total: 0
    }];

    const rows = products.map((p: any, index: number) => {
      const qty = Number(p.qty || 0);
      const unit = Number(p.unitPrice || 0);
      const total = Number(p.total || qty * unit);
      return `<tr>
        <td>${index + 1}</td>
        <td>${this.escapePrint(p.name)}</td>
        <td>${this.escapePrint(p.sku || '—')}</td>
        <td>${qty}</td>
        <td>₹${unit.toLocaleString('en-IN')}</td>
        <td>₹${total.toLocaleString('en-IN')}</td>
      </tr>`;
    }).join('');

    const subtotal = products.reduce((sum: number, p: any) =>
      sum + Number(p.total || (Number(p.qty || 0) * Number(p.unitPrice || 0))), 0
    );

    this.openPrintWindow(
      `Invoice - ${s.userId}`,
      `
      <div class="invoice">
        <div class="invoice-head">
          <div>
            <div class="brand">CARBONOVA</div>
            <div class="subtitle">FARMER PRODUCT INVOICE</div>
          </div>
          <div class="invoice-number">
            <span>Invoice No.</span>
            <b>INV-${s.id}</b>
            <small>Shipment: SHIP-${s.id}</small>
          </div>
        </div>

        <div class="invoice-grid">
          <div>
            <span>Bill To</span>
            <b>${this.escapePrint(s.farmer)}</b>
            <div>${this.escapePrint(s.userId)}</div>
            <div>${this.escapePrint(s.mobile)}</div>
          </div>
          <div>
            <span>Ship To</span>
            <b>${this.escapePrint(s.farmer)}</b>
            <div>${this.escapePrint(s.address)}</div>
            <div>${this.escapePrint(s.city)}, ${this.escapePrint(s.state)} - ${this.escapePrint(s.pincode)}</div>
          </div>
        </div>

        <table>
          <thead><tr><th>#</th><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>

        <div class="invoice-total">
          <span>Product Value</span>
          <strong>₹${subtotal.toLocaleString('en-IN')}</strong>
        </div>

        <div class="invoice-meta">
          <div><span>Shipping Mode</span><b>${this.escapePrint((s as any).shippingMode || 'Not Set')}</b></div>
          <div><span>Tracking</span><b>${this.escapePrint(s.tracking || 'Not Assigned')}</b></div>
          <div><span>Status</span><b>${this.escapePrint(s.status)}</b></div>
        </div>

        <div class="invoice-note">
          This document is generated for Carbonova farmer product fulfilment.
          Product pricing and tax information should be connected to the final billing data from the backend before production use.
        </div>
      </div>
      `
    );
  }

  private escapePrint(value: any): string {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  private openPrintWindow(title: string, body: string): void {
    const win = window.open('', '_blank', 'width=900,height=700');
    if (!win) return;

    win.document.open();
    win.document.write(`
      <!doctype html>
      <html>
      <head>
        <title>${this.escapePrint(title)}</title>
        <meta charset="utf-8">
        <style>
          *{box-sizing:border-box}
          body{margin:0;padding:30px;font-family:Arial,Helvetica,sans-serif;color:#24382a;background:#fff}
          .brand{font:700 26px Georgia,serif;letter-spacing:1px;color:#315b3d}
          .subtitle{font-size:10px;letter-spacing:1.2px;color:#738078;font-weight:700;margin-top:3px}
          .label{width:100%;max-width:420px;margin:auto;border:2px solid #315b3d;padding:22px}
          .label-divider{border-top:1px dashed #aeb7b0;margin:16px 0}
          .section-title{font-size:10px;font-weight:800;letter-spacing:1px;color:#738078;margin-bottom:6px}
          .recipient{font-size:20px;font-weight:800;margin-bottom:5px}
          .pincode{font-size:18px;font-weight:800;margin-top:5px}
          .meta{display:grid;grid-template-columns:1fr 1fr;gap:10px}
          .meta span,.invoice-grid span,.invoice-number span,.invoice-meta span{display:block;font-size:9px;text-transform:uppercase;color:#89938c;font-weight:700;margin-bottom:3px}
          .meta b{font-size:11px}
          table{width:100%;border-collapse:collapse;margin-top:12px;font-size:11px}
          th,td{border-bottom:1px solid #e1e5e1;padding:7px;text-align:left}
          th{font-size:9px;text-transform:uppercase;color:#738078}
          .footer-note{text-align:center;margin-top:18px;font-size:9px;color:#89938c}
          .invoice{max-width:900px;margin:auto}
          .invoice-head{display:flex;justify-content:space-between;border-bottom:2px solid #315b3d;padding-bottom:16px}
          .invoice-number{text-align:right}
          .invoice-number b{display:block;font-size:18px;color:#315b3d}
          .invoice-number small{display:block;margin-top:5px;color:#738078}
          .invoice-grid{display:grid;grid-template-columns:1fr 1fr;gap:30px;margin:22px 0}
          .invoice-grid b{display:block;font-size:14px;margin-bottom:4px}
          .invoice-total{margin:18px 0 8px auto;width:280px;display:flex;justify-content:space-between;font-size:15px;border-top:2px solid #315b3d;padding-top:10px}
          .invoice-total strong{font-size:19px}
          .invoice-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;background:#f5f7f4;padding:12px;margin-top:16px}
          .invoice-note{margin-top:24px;padding-top:12px;border-top:1px solid #ddd;font-size:9px;color:#778078;line-height:1.5}
          @media print{body{padding:0}.label{max-width:none;border:2px solid #315b3d}.invoice{width:100%}}
        </style>
      </head>
      <body>${body}
        <script>
          window.onload=function(){setTimeout(function(){window.print()},250)}
        <\/script>
      </body>
      </html>
    `);
    win.document.close();
  }

}
