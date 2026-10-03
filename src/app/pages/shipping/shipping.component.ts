import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { PageHeaderComponent } from '../../shared/page-header.component';
import { PaginationComponent } from '../../shared/pagination/pagination.component';
import { DataService, AdminOrder, AdminOrderItem } from '../../core/data.service';

@Component({
  selector: 'app-shipping',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, PaginationComponent],
  templateUrl: './shipping.component.html',
  styleUrl: './shipping.component.scss'
})
export class ShippingComponent implements OnInit {
  tab: 'all' | 'pending' | 'packed' | 'shipped' | 'delivered' = 'all';
  orderTypeFilter: 'ALL' | 'PACKAGE' | 'DIRECT_PRODUCT' = 'ALL';
  page = 1;
  pageSize = 10;
  search = '';
  loading = false;

  // Modals
  showProcessModal = false;
  showDetailsModal = false;
  selectedOrder: AdminOrder | null = null;

  // Process shipment form state
  processForm = {
    order_status: 'PACKED' as AdminOrder['order_status'],
    shipping_mode: 'Courier',
    courier_name: 'DTDC Express',
    tracking_number: '',
    notes: ''
  };

  courierPartners = [
    'DTDC Express',
    'Delhivery',
    'India Post (Speed Post)',
    'Blue Dart',
    'Ekart Logistics',
    'VRL Logistics',
    'Safechem Transport',
    'Local Farm Van'
  ];

  shippingModes = ['Courier', 'India Post', 'Transport', 'Local Delivery', 'Self Pickup'];

  constructor(public d: DataService) {}

  ngOnInit(): void {
    this.loadOrders();
  }

  loadOrders(): void {
    this.loading = true;
    this.d.loadOrders().subscribe({
      next: () => this.loading = false,
      error: () => this.loading = false
    });
  }

  get baseOrders(): AdminOrder[] {
    let list = this.d.orders;

    if (this.tab !== 'all') {
      const targetStatus = this.tab.toUpperCase();
      list = list.filter(o => o.order_status === targetStatus);
    }

    if (this.orderTypeFilter !== 'ALL') {
      list = list.filter(o => o.order_type === this.orderTypeFilter);
    }

    if (this.search.trim()) {
      const q = this.search.trim().toLowerCase();
      list = list.filter(o =>
        o.order_number.toLowerCase().includes(q) ||
        o.customer_name.toLowerCase().includes(q) ||
        o.userid.toLowerCase().includes(q) ||
        o.mobile.includes(q) ||
        o.city.toLowerCase().includes(q) ||
        (o.tracking_number && o.tracking_number.toLowerCase().includes(q))
      );
    }

    return list;
  }

  get pagedOrders(): AdminOrder[] {
    return this.baseOrders.slice((this.page - 1) * this.pageSize, this.page * this.pageSize);
  }

  get totalOrders(): number {
    return this.baseOrders.length;
  }

  get pendingCount(): number {
    return this.d.orders.filter(o => o.order_status === 'PENDING').length;
  }

  get packedCount(): number {
    return this.d.orders.filter(o => o.order_status === 'PACKED').length;
  }

  get shippedCount(): number {
    return this.d.orders.filter(o => o.order_status === 'SHIPPED').length;
  }

  get deliveredCount(): number {
    return this.d.orders.filter(o => o.order_status === 'DELIVERED').length;
  }

  get totalRewardPointsDistributed(): number {
    return this.d.orders.reduce((sum, o) => sum + (o.total_reward_points || 0), 0);
  }

  get totalRevenue(): number {
    return this.d.orders.reduce((sum, o) => sum + (o.total_amount || 0), 0);
  }

  onPageChange(p: number): void {
    this.page = p;
  }

  onPageSizeChange(s: number): void {
    this.pageSize = s;
    this.page = 1;
  }

  changeTab(t: typeof this.tab): void {
    this.tab = t;
    this.page = 1;
  }

  openOrderDetails(order: AdminOrder): void {
    this.selectedOrder = order;
    this.showDetailsModal = true;
  }

  closeOrderDetails(): void {
    this.showDetailsModal = false;
    this.selectedOrder = null;
  }

  openProcess(order: AdminOrder): void {
    this.selectedOrder = order;
    this.processForm = {
      order_status: order.order_status === 'PENDING' ? 'PACKED' : order.order_status,
      shipping_mode: order.shipping_mode || 'Courier',
      courier_name: order.courier_name || 'DTDC Express',
      tracking_number: order.tracking_number || '',
      notes: ''
    };
    this.showProcessModal = true;
  }

  closeProcess(): void {
    this.showProcessModal = false;
    this.selectedOrder = null;
  }

  saveProcess(): void {
    if (!this.selectedOrder) return;

    this.d.updateOrderShipping(this.selectedOrder.order_id, {
      shipping_status: this.processForm.order_status,
      shipping_mode: this.processForm.shipping_mode,
      courier_name: this.processForm.courier_name,
      tracking_number: this.processForm.tracking_number,
      notes: this.processForm.notes
    }).subscribe(() => {
      this.loadOrders();
    });

    this.closeProcess();
  }

  quickStatus(order: AdminOrder, newStatus: AdminOrder['order_status']): void {
    this.d.updateOrderStatus(order.order_id, newStatus).subscribe(() => {
      this.loadOrders();
    });
  }

  printShippingLabel(order: AdminOrder): void {
    const items = order.items || [];
    const itemRows = items.map(p =>
      `<tr>
        <td><b>${this.escapePrint(p.product_name)}</b><br><small style="color:#777">${this.escapePrint(p.scientific_name || '')}</small></td>
        <td style="text-align:center">${p.quantity}</td>
        <td style="text-align:right">★ ${p.total_reward_points} RP</td>
      </tr>`
    ).join('');

    this.openPrintWindow(
      `Shipping Label - ${order.order_number}`,
      `
      <div class="label">
        <div class="brand">CARBONOVA ECO SYSTEM</div>
        <div class="subtitle">FARMER &amp; CONSUMER PLANT LOGISTICS</div>
        <div class="label-divider"></div>

        <div class="meta-row">
          <div><span>Order No:</span><b>${this.escapePrint(order.order_number)}</b></div>
          <div><span>Type:</span><b>${order.order_type === 'PACKAGE' ? 'Package Plant Kit' : 'Direct Plant Order'}</b></div>
          <div><span>Order Date:</span><b>${this.escapePrint(order.order_date)}</b></div>
        </div>

        <div class="label-divider"></div>

        <div class="section-title">DELIVER TO:</div>
        <div class="recipient">${this.escapePrint(order.customer_name)}</div>
        <div class="phone">📱 ${this.escapePrint(order.mobile)} | Farmer ID: ${this.escapePrint(order.userid)}</div>
        <div class="address">${this.escapePrint(order.address)}</div>
        <div class="address">${this.escapePrint(order.city)}, ${this.escapePrint(order.state)} - <b>${this.escapePrint(order.pincode)}</b></div>

        <div class="label-divider"></div>

        <div class="shipping-info">
          <div><span>Courier:</span><b>${this.escapePrint(order.courier_name || 'Assigned')}</b></div>
          <div><span>Mode:</span><b>${this.escapePrint(order.shipping_mode || 'Surface')}</b></div>
          <div><span>Tracking / AWB:</span><b style="font-size:14px;letter-spacing:1px">${this.escapePrint(order.tracking_number || 'GEN-PENDING')}</b></div>
        </div>

        <div class="label-divider"></div>

        <div class="section-title">ENCLOSED PLANTS &amp; PRODUCTS:</div>
        <table class="item-table">
          <thead>
            <tr>
              <th>Plant / Product Name</th>
              <th style="text-align:center">Qty</th>
              <th style="text-align:right">Points</th>
            </tr>
          </thead>
          <tbody>
            ${itemRows}
          </tbody>
        </table>

        <div class="footer-note">
          🌱 Live Agroforestry Saplings • Handle With Care • Keep Upright • Carbonova Supply Chain
        </div>
      </div>
      `
    );
  }

  printInvoice(order: AdminOrder): void {
    const items = order.items || [];
    let subtotal = 0;
    const rows = items.map((p, index) => {
      subtotal += p.total_price;
      return `<tr>
        <td>${index + 1}</td>
        <td>
          <b>${this.escapePrint(p.product_name)}</b>
          <div style="font-size:10px;color:#666">${this.escapePrint(p.scientific_name || '')}</div>
        </td>
        <td>${p.quantity}</td>
        <td>₹${p.unit_price.toLocaleString('en-IN')}</td>
        <td>₹${p.unit_dp.toLocaleString('en-IN')}</td>
        <td>★ ${p.total_reward_points} RP</td>
        <td style="text-align:right;font-weight:bold">₹${p.total_price.toLocaleString('en-IN')}</td>
      </tr>`;
    }).join('');

    this.openPrintWindow(
      `Tax Invoice - ${order.order_number}`,
      `
      <div class="invoice">
        <div class="invoice-head">
          <div>
            <div class="brand">CARBONOVA WORLD</div>
            <div class="subtitle">AGRO-FORESTRY &amp; CARBON TRADING PRIVATE LIMITED</div>
            <div style="font-size:11px;color:#666;margin-top:4px">Reg. Office: Raipur, Chhattisgarh, India • support@carbonovaworld.com</div>
          </div>
          <div class="invoice-number">
            <span>TAX INVOICE</span>
            <b>${this.escapePrint(order.order_number)}</b>
            <small>Date: ${this.escapePrint(order.order_date)}</small>
            <div style="margin-top:4px"><span class="badge-paid">PAID</span></div>
          </div>
        </div>

        <div class="invoice-grid">
          <div>
            <span>Billed / Delivered To:</span>
            <b style="font-size:14px">${this.escapePrint(order.customer_name)}</b>
            <div>Farmer ID: ${this.escapePrint(order.userid)}</div>
            <div>Contact: ${this.escapePrint(order.mobile)}</div>
            <div style="margin-top:4px">${this.escapePrint(order.address)}</div>
            <div>${this.escapePrint(order.city)}, ${this.escapePrint(order.state)} - ${this.escapePrint(order.pincode)}</div>
          </div>
          <div style="text-align:right">
            <span>Logistics &amp; Dispatch:</span>
            <b>${this.escapePrint(order.courier_name || 'Standard Courier')}</b>
            <div>Mode: ${this.escapePrint(order.shipping_mode || 'Road Transport')}</div>
            <div>Tracking AWB: ${this.escapePrint(order.tracking_number || 'N/A')}</div>
            <div>Order Category: <b>${order.order_type === 'PACKAGE' ? 'Farmer Package Kit' : 'Direct Consumer Purchase'}</b></div>
          </div>
        </div>

        <table class="inv-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Item Description</th>
              <th>Qty</th>
              <th>MRP</th>
              <th>DP Price</th>
              <th>Reward Points</th>
              <th style="text-align:right">Total</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>

        <div class="totals-wrap">
          <div class="reward-box">
            <span>Reward Points Credited:</span>
            <strong style="color:#8a6331;font-size:16px">★ ${order.total_reward_points} Reward Points</strong>
            <small style="display:block;color:#777;font-size:10px">Points available in consumer loyalty wallet for future discounts.</small>
          </div>
          <div class="grand-total">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
              <span>Subtotal:</span>
              <b>₹${order.total_amount.toLocaleString('en-IN')}</b>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
              <span>Delivery &amp; Packaging:</span>
              <b style="color:#2a7a42">FREE</b>
            </div>
            <div style="display:flex;justify-content:space-between;border-top:2px solid #203d2b;padding-top:8px;font-size:16px">
              <span>Total Payable:</span>
              <strong style="color:#203d2b">₹${order.total_amount.toLocaleString('en-IN')}</strong>
            </div>
          </div>
        </div>

        <div class="invoice-note">
          Terms &amp; Conditions: Live saplings are conditioned for carbon farming. Inspect upon arrival.
          This is an electronically generated invoice valid without physical signature.
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
    const win = window.open('', '_blank', 'width=920,height=750');
    if (!win) return;

    win.document.open();
    win.document.write(`
      <!doctype html>
      <html>
      <head>
        <title>${this.escapePrint(title)}</title>
        <meta charset="utf-8">
        <style>
          * { box-sizing: border-box; }
          body { margin: 0; padding: 25px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #222; background: #fff; }
          .brand { font-size: 24px; font-weight: 800; color: #26422f; letter-spacing: 1px; }
          .subtitle { font-size: 10px; font-weight: 700; color: #6d7b71; letter-spacing: 1px; margin-top: 2px; }
          .label { width: 100%; max-width: 460px; margin: auto; border: 2px solid #26422f; padding: 20px; border-radius: 8px; }
          .label-divider { border-top: 1px dashed #999; margin: 12px 0; }
          .meta-row { display: flex; justify-content: space-between; font-size: 11px; }
          .meta-row span { color: #666; margin-right: 4px; }
          .section-title { font-size: 10px; font-weight: 800; color: #666; letter-spacing: 1px; margin-bottom: 4px; }
          .recipient { font-size: 20px; font-weight: 800; color: #1f4b32; }
          .phone { font-size: 12px; font-weight: 700; margin: 3px 0; }
          .address { font-size: 13px; line-height: 1.4; color: #333; }
          .shipping-info { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; font-size: 11px; }
          .shipping-info span { display: block; font-size: 9px; color: #666; text-transform: uppercase; }
          .item-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 6px; }
          .item-table th, .item-table td { border-bottom: 1px solid #ddd; padding: 6px 4px; text-align: left; }
          .item-table th { font-size: 9px; text-transform: uppercase; color: #666; }
          .footer-note { text-align: center; margin-top: 14px; font-size: 9px; color: #666; line-height: 1.4; }
          .invoice { max-width: 860px; margin: auto; border: 1px solid #eee; padding: 30px; border-radius: 8px; }
          .invoice-head { display: flex; justify-content: space-between; border-bottom: 2px solid #26422f; padding-bottom: 16px; }
          .invoice-number { text-align: right; }
          .invoice-number span { font-size: 11px; color: #666; font-weight: 800; }
          .invoice-number b { display: block; font-size: 20px; color: #26422f; margin: 2px 0; }
          .badge-paid { display: inline-block; background: #e3f2e6; color: #206d33; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 4px; }
          .invoice-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; font-size: 12px; line-height: 1.5; }
          .invoice-grid span { font-size: 10px; text-transform: uppercase; color: #888; font-weight: 800; display: block; margin-bottom: 3px; }
          .inv-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 12px; }
          .inv-table th, .inv-table td { padding: 10px 8px; border-bottom: 1px solid #e5e5e5; text-align: left; }
          .inv-table th { background: #f8faf8; font-size: 10px; text-transform: uppercase; color: #555; }
          .totals-wrap { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 15px; }
          .reward-box { background: #faf6ed; border: 1px solid #ebd9b5; padding: 12px 16px; border-radius: 8px; width: 340px; }
          .reward-box span { font-size: 11px; font-weight: 700; color: #72521f; display: block; }
          .grand-total { width: 280px; font-size: 13px; }
          .invoice-note { margin-top: 30px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 10px; color: #777; text-align: center; }
          @media print {
            body { padding: 0; }
            .invoice { border: none; padding: 0; }
            .label { max-width: none; border: 2px solid #000; }
          }
        </style>
      </head>
      <body>
        ${body}
        <script>
          window.onload = function() { setTimeout(function(){ window.print(); }, 250); };
        <\/script>
      </body>
      </html>
    `);
    win.document.close();
  }
}
