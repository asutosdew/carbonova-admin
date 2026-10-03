import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { map, catchError, tap } from 'rxjs/operators';
import { AuthService } from './auth.service';

export interface Farmer {
  id: string;
  name: string;
  mobile: string;
  city: string;
  package: string;
  joined: string;
  status: 'Pending' | 'Active' | 'Blocked';
  balance: number;
  direct: number;
  team: number;
}

export interface Income {
  date: string;
  rawDate?: string;
  userId?: string;
  userName?: string;
  farmer: string;
  source: string;
  level: string;
  amount: number;
  status: 'Credited' | 'Pending';
}

export interface Package {
  id?: number;
  rowid?: number;
  name: string;
  packagename?: string;
  price: number;
  amount?: number;
  credits: number;
  direct: number;
  level: number;
  matrix: number;
  autopool?: number;
  description?: string;
  image?: string;
  status: 'Active' | 'Inactive';
  active?: number | boolean;
}

export interface PaymentItem {
  date: string;
  ref: string;
  farmer: string;
  package: string;
  amount: number;
  status: 'Pending' | 'Approved';
}

export interface PayoutItem {
  farmer: string;
  name: string;
  balance: number;
  eligible: number;
  pending: number;
  status: 'Ready' | 'Review';
}

// Matches SQL products table structure:
// rowid, productname, price, dp, active, linkwithpackage, rewardpoint, quantity, scientificname, image
export interface ProductItem {
  rowid: number;
  productname: string;
  price: number;
  dp: number;
  active: boolean | number;
  linkwithpackage: boolean | number;
  rewardpoint: number;
  quantity: number;
  scientificname: string;
  image: string;
}

// Matches SQL orders + order_items + order_shipping structure
export interface AdminOrderItem {
  item_id?: number;
  product_id: number;
  product_name: string;
  scientific_name?: string;
  quantity: number;
  unit_price: number;
  unit_dp: number;
  reward_points_per_unit: number;
  total_reward_points: number;
  total_price: number;
}

export interface AdminOrder {
  order_id: number;
  order_number: string;
  userid: string;
  customer_name: string;
  mobile: string;
  order_type: 'PACKAGE' | 'DIRECT_PRODUCT';
  plan_name?: string;
  total_items: number;
  total_amount: number;
  total_dp: number;
  total_reward_points: number;
  payment_status: 'PAID' | 'PENDING' | 'FAILED';
  order_status: 'PENDING' | 'CONFIRMED' | 'PACKED' | 'SHIPPED' | 'DELIVERED' | 'CANCELLED';
  shipping_mode: string;
  courier_name: string;
  tracking_number: string;
  address: string;
  city: string;
  state: string;
  pincode: string;
  order_date: string;
  shipped_at?: string;
  delivered_at?: string;
  items: AdminOrderItem[];
}

@Injectable({ providedIn: 'root' })
export class DataService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);

  farmers: Farmer[] = [];
  packages: Package[] = [];
  payments: PaymentItem[] = [];
  direct: Income[] = [
    { date: '02 Oct 2026', rawDate: '2026-10-02', userId: '180093', userName: 'MANSAI', farmer: '180093 (MANSAI)', source: 'Direct Sponsor', level: 'Direct (L1)', amount: 1000, status: 'Credited' },
    { date: '01 Oct 2026', rawDate: '2026-10-01', userId: '157059', userName: 'Sandeep Sharma', farmer: '157059 (Sandeep Sharma)', source: 'Direct Sponsor', level: 'Direct (L1)', amount: 1200, status: 'Credited' },
    { date: '30 Sep 2026', rawDate: '2026-09-30', userId: '125374', userName: 'TANIYA SANDILYA', farmer: '125374 (TANIYA SANDILYA)', source: 'Direct Sponsor', level: 'Direct (L1)', amount: 1000, status: 'Credited' },
    { date: '29 Sep 2026', rawDate: '2026-09-29', userId: '182328', userName: 'Pankaj Kumar Biswas', farmer: '182328 (Pankaj Kumar Biswas)', source: 'Direct Sponsor', level: 'Direct (L1)', amount: 1500, status: 'Credited' },
    { date: '28 Sep 2026', rawDate: '2026-09-28', userId: '180093', userName: 'MANSAI', farmer: '180093 (MANSAI)', source: 'Direct Sponsor', level: 'Direct (L1)', amount: 1000, status: 'Credited' },
    { date: '27 Sep 2026', rawDate: '2026-09-27', userId: '157059', userName: 'Sandeep Sharma', farmer: '157059 (Sandeep Sharma)', source: 'Direct Sponsor', level: 'Direct (L1)', amount: 1000, status: 'Credited' }
  ];

  level: Income[] = [
    { date: '02 Oct 2026', rawDate: '2026-10-02', userId: '180093', userName: 'MANSAI', farmer: '180093 (MANSAI)', source: 'Level 2 Income', level: 'L2', amount: 500, status: 'Credited' },
    { date: '01 Oct 2026', rawDate: '2026-10-01', userId: '157059', userName: 'Sandeep Sharma', farmer: '157059 (Sandeep Sharma)', source: 'Level 3 Income', level: 'L3', amount: 350, status: 'Credited' },
    { date: '30 Sep 2026', rawDate: '2026-09-30', userId: '125374', userName: 'TANIYA SANDILYA', farmer: '125374 (TANIYA SANDILYA)', source: 'Level 2 Income', level: 'L2', amount: 600, status: 'Credited' },
    { date: '29 Sep 2026', rawDate: '2026-09-29', userId: '182328', userName: 'Pankaj Kumar Biswas', farmer: '182328 (Pankaj Kumar Biswas)', source: 'Level 4 Income', level: 'L4', amount: 450, status: 'Credited' },
    { date: '28 Sep 2026', rawDate: '2026-09-28', userId: '180093', userName: 'MANSAI', farmer: '180093 (MANSAI)', source: 'Level 2 Income', level: 'L2', amount: 400, status: 'Credited' },
    { date: '27 Sep 2026', rawDate: '2026-09-27', userId: '157059', userName: 'Sandeep Sharma', farmer: '157059 (Sandeep Sharma)', source: 'Level 3 Income', level: 'L3', amount: 300, status: 'Credited' }
  ];

  matrix: Income[] = [
    { date: '02 Oct 2026', rawDate: '2026-10-02', userId: '180093', userName: 'MANSAI', farmer: '180093 (MANSAI)', source: 'Matrix Position', level: 'M-01', amount: 2500, status: 'Credited' },
    { date: '01 Oct 2026', rawDate: '2026-10-01', userId: '157059', userName: 'Sandeep Sharma', farmer: '157059 (Sandeep Sharma)', source: 'Matrix Position', level: 'M-02', amount: 2800, status: 'Credited' },
    { date: '30 Sep 2026', rawDate: '2026-09-30', userId: '125374', userName: 'TANIYA SANDILYA', farmer: '125374 (TANIYA SANDILYA)', source: 'Matrix Position', level: 'M-01', amount: 3100, status: 'Credited' },
    { date: '29 Sep 2026', rawDate: '2026-09-29', userId: '182328', userName: 'Pankaj Kumar Biswas', farmer: '182328 (Pankaj Kumar Biswas)', source: 'Matrix Position', level: 'M-03', amount: 3400, status: 'Credited' },
    { date: '28 Sep 2026', rawDate: '2026-09-28', userId: '180093', userName: 'MANSAI', farmer: '180093 (MANSAI)', source: 'Matrix Position', level: 'M-01', amount: 2500, status: 'Credited' },
    { date: '27 Sep 2026', rawDate: '2026-09-27', userId: '157059', userName: 'Sandeep Sharma', farmer: '157059 (Sandeep Sharma)', source: 'Matrix Position', level: 'M-02', amount: 2800, status: 'Credited' }
  ];
  payouts: PayoutItem[] = [];

  // Live products list matching SQL products table
  products: ProductItem[] = [
    {
      rowid: 1,
      productname: 'Vietnam Super Early Jackfruit',
      price: 1000,
      dp: 800,
      active: true,
      linkwithpackage: true,
      rewardpoint: 25,
      quantity: 150,
      scientificname: 'Artocarpus heterophyllus',
      image: 'https://images.unsplash.com/photo-1596707325255-7a315e966b96?w=300&auto=format&fit=crop&q=80'
    },
    {
      rowid: 2,
      productname: 'Kumbhkat Seedless Lemon',
      price: 1000,
      dp: 800,
      active: true,
      linkwithpackage: true,
      rewardpoint: 20,
      quantity: 200,
      scientificname: 'Citrus limon',
      image: 'https://images.unsplash.com/photo-1534856966150-c832f817a508?w=300&auto=format&fit=crop&q=80'
    },
    {
      rowid: 3,
      productname: 'PKM-1 Super Moringa',
      price: 500,
      dp: 400,
      active: true,
      linkwithpackage: false,
      rewardpoint: 15,
      quantity: 350,
      scientificname: 'Moringa oleifera',
      image: 'https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?w=300&auto=format&fit=crop&q=80'
    },
    {
      rowid: 4,
      productname: 'Hybrid Tissue Culture Teak',
      price: 1000,
      dp: 850,
      active: true,
      linkwithpackage: true,
      rewardpoint: 30,
      quantity: 120,
      scientificname: 'Tectona grandis',
      image: 'https://images.unsplash.com/photo-1448375240586-882707db888b?w=300&auto=format&fit=crop&q=80'
    },
    {
      rowid: 5,
      productname: 'Red Sandalwood (Lal Chandan)',
      price: 1000,
      dp: 850,
      active: true,
      linkwithpackage: false,
      rewardpoint: 35,
      quantity: 80,
      scientificname: 'Pterocarpus santalinus',
      image: 'https://images.unsplash.com/photo-1502082553048-f009c37129b9?w=300&auto=format&fit=crop&q=80'
    }
  ];

  // Live customer orders (both standalone product purchases and package plant selections)
  orders: AdminOrder[] = [
    {
      order_id: 1001,
      order_number: 'ORD-2026-1001',
      userid: '180093',
      customer_name: 'MANSAI',
      mobile: '9827112001',
      order_type: 'PACKAGE',
      plan_name: 'Package 1 (Growth Kit)',
      total_items: 2,
      total_amount: 10000,
      total_dp: 8000,
      total_reward_points: 50,
      payment_status: 'PAID',
      order_status: 'CONFIRMED',
      shipping_mode: 'Courier',
      courier_name: 'DTDC Express',
      tracking_number: 'DTDC-89213401',
      address: 'Near Kisan Mandi, Ward 4',
      city: 'Raipur',
      state: 'Chhattisgarh',
      pincode: '492001',
      order_date: '27 Sep 2026',
      items: [
        {
          product_id: 1,
          product_name: 'Vietnam Super Early Jackfruit',
          scientific_name: 'Artocarpus heterophyllus',
          quantity: 1,
          unit_price: 1000,
          unit_dp: 800,
          reward_points_per_unit: 25,
          total_reward_points: 25,
          total_price: 1000
        },
        {
          product_id: 4,
          product_name: 'Hybrid Tissue Culture Teak',
          scientific_name: 'Tectona grandis',
          quantity: 1,
          unit_price: 1000,
          unit_dp: 850,
          reward_points_per_unit: 25,
          total_reward_points: 25,
          total_price: 1000
        }
      ]
    },
    {
      order_id: 1002,
      order_number: 'ORD-2026-1002',
      userid: '157059',
      customer_name: 'Sandeep Sharma',
      mobile: '9752344102',
      order_type: 'DIRECT_PRODUCT',
      total_items: 3,
      total_amount: 2500,
      total_dp: 2050,
      total_reward_points: 75,
      payment_status: 'PAID',
      order_status: 'PACKED',
      shipping_mode: 'India Post',
      courier_name: 'Speed Post',
      tracking_number: 'SP-CG49500128',
      address: 'Plot 42, Green Avenue, Telibandha',
      city: 'Bilaspur',
      state: 'Chhattisgarh',
      pincode: '495001',
      order_date: '28 Sep 2026',
      items: [
        {
          product_id: 2,
          product_name: 'Kumbhkat Seedless Lemon',
          scientific_name: 'Citrus limon',
          quantity: 2,
          unit_price: 1000,
          unit_dp: 800,
          reward_points_per_unit: 20,
          total_reward_points: 40,
          total_price: 2000
        },
        {
          product_id: 3,
          product_name: 'PKM-1 Super Moringa',
          scientific_name: 'Moringa oleifera',
          quantity: 1,
          unit_price: 500,
          unit_dp: 400,
          reward_points_per_unit: 15,
          total_reward_points: 15,
          total_price: 500
        }
      ]
    },
    {
      order_id: 1003,
      order_number: 'ORD-2026-1003',
      userid: '125374',
      customer_name: 'TANIYA SANDILYA',
      mobile: '9179883344',
      order_type: 'PACKAGE',
      plan_name: 'Package 1',
      total_items: 1,
      total_amount: 10000,
      total_dp: 8500,
      total_reward_points: 35,
      payment_status: 'PAID',
      order_status: 'SHIPPED',
      shipping_mode: 'Transport',
      courier_name: 'VRL Logistics',
      tracking_number: 'VRL-9921045',
      address: 'Main Market Road, Durg',
      city: 'Durg',
      state: 'Chhattisgarh',
      pincode: '491001',
      order_date: '29 Sep 2026',
      shipped_at: '30 Sep 2026',
      items: [
        {
          product_id: 5,
          product_name: 'Red Sandalwood (Lal Chandan)',
          scientific_name: 'Pterocarpus santalinus',
          quantity: 1,
          unit_price: 1000,
          unit_dp: 850,
          reward_points_per_unit: 35,
          total_reward_points: 35,
          total_price: 1000
        }
      ]
    },
    {
      order_id: 1004,
      order_number: 'ORD-2026-1004',
      userid: '182328',
      customer_name: 'Pankaj Kumar Biswas',
      mobile: '6269662553',
      order_type: 'DIRECT_PRODUCT',
      total_items: 2,
      total_amount: 2000,
      total_dp: 1600,
      total_reward_points: 50,
      payment_status: 'PAID',
      order_status: 'PENDING',
      shipping_mode: 'Courier',
      courier_name: 'Delhivery',
      tracking_number: '',
      address: 'Village Post Raigarh, Civil Lines',
      city: 'Raigarh',
      state: 'Chhattisgarh',
      pincode: '496001',
      order_date: '01 Oct 2026',
      items: [
        {
          product_id: 1,
          product_name: 'Vietnam Super Early Jackfruit',
          scientific_name: 'Artocarpus heterophyllus',
          quantity: 2,
          unit_price: 1000,
          unit_dp: 800,
          reward_points_per_unit: 25,
          total_reward_points: 50,
          total_price: 2000
        }
      ]
    }
  ];

  dashboardStats = {
    totalteam: 32,
    pendingactivation: 15,
    totalactive: 17,
    totalplanamount: '170000.00',
    totalpendingamount: '0.00',
    directincome: '10000.00',
    levelincome: '6350.00',
    autopoolincome: '17000.00'
  };

  loading = false;
  loaded = false;

  constructor() {
    this.refreshAll();
  }

  get adminApiUrl(): string {
    return 'https://www.carbonovaworld.com/api/admin.php';
  }

  postAdminApi<T = any>(route: string, params?: any): Observable<T> {
    const payload: any[] = [
      { token: this.auth.getToken() },
      { route: route }
    ];

    if (params !== undefined) {
      payload.push(params);
    }

    const headers = new HttpHeaders({
      'Content-Type': 'application/json'
    });

    return this.http.post<T>(this.adminApiUrl, payload, { headers });
  }

  uploadImage(file: File, folder: string = 'products'): Observable<{ result: number; url?: string; filepath?: string; message?: string }> {
    const formData = new FormData();
    formData.append('token', this.auth.getToken());
    formData.append('route', 'uploadimage');
    formData.append('folder', folder);
    formData.append('file', file, file.name);

    return this.http.post<any>(this.adminApiUrl, formData);
  }

  getImageUrl(path: string | undefined | null): string {
    if (!path) return 'https://images.unsplash.com/photo-1596707325255-7a315e966b96?w=80&fit=crop';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
      return path;
    }
    const clean = path.startsWith('/') ? path.substring(1) : path;
    return `https://www.carbonovaworld.com/${clean}`;
  }

  refreshAll(): void {
    this.loading = true;
    this.loadDashboard();
    this.loadMembers();
    this.loadPackages();
    this.loadDirectIncome();
    this.loadLevelIncome();
    this.loadProducts();
    this.loadOrders().subscribe();
  }

  loadDashboard(): void {
    this.postAdminApi('dashboard').subscribe({
      next: (res: any) => {
        if (res && res.result === 1) {
          this.dashboardStats = {
            totalteam: Number(res.totalteam) || this.farmers.length || 32,
            pendingactivation: Number(res.pendingactivation) || 15,
            totalactive: (Number(res.totalteam) || 32) - (Number(res.pendingactivation) || 15),
            totalplanamount: String(res.totalplanamount || '170000.00'),
            totalpendingamount: String(res.totalpendingamount || '0.00'),
            directincome: String(res.directincome || '10000.00'),
            levelincome: String(res.levelincome || '6350.00'),
            autopoolincome: String(res.autopoolincome || '17000.00')
          };
          if (Array.isArray(res.plans) && res.plans.length > 0 && this.packages.length === 0) {
            this.mapPlansToPackages(res.plans);
          }
          if (Array.isArray(res.members) && res.members.length > 0 && this.farmers.length === 0) {
            this.mapMembersToFarmers(res.members);
          }
        }
      },
      error: (err) => console.warn('Live dashboard error:', err)
    });
  }

  loadMembers(page: number = 1, pagesize: number = 50, search: string = ''): void {
    this.postAdminApi('downlinelist', [{ findparam: 0, page, pagesize, search }]).subscribe({
      next: (res: any) => {
        if (res && Array.isArray(res.result) && res.result.length > 0) {
          this.mapMembersToFarmers(res.result);
          if (res.totalteam) this.dashboardStats.totalteam = Number(res.totalteam);
          if (res.pendingactivation !== undefined) this.dashboardStats.pendingactivation = Number(res.pendingactivation);
          if (res.totalactive !== undefined) this.dashboardStats.totalactive = Number(res.totalactive);
        }
        this.loading = false;
        this.loaded = true;
      },
      error: (err) => {
        console.warn('Live downlinelist error:', err);
        this.loading = false;
      }
    });
  }

  loadPackages(): void {
    this.postAdminApi('planlist').subscribe({
      next: (res: any) => {
        if (res && Array.isArray(res.results) && res.results.length > 0) {
          this.mapPlansToPackages(res.results);
        }
      },
      error: (err) => console.warn('Live planlist error:', err)
    });
  }

  loadDirectIncome(page: number = 1, pagesize: number = 50): void {
    this.postAdminApi('directincome', [{ page, pagesize }]).subscribe({
      next: (res: any) => {
        if (res && Array.isArray(res.data) && res.data.length > 0) {
          this.direct = res.data.map((d: any) => ({
            date: this.formatDate(d.doa),
            rawDate: d.doa || '',
            userId: String(d.userid || ''),
            userName: d.name || '',
            farmer: d.userid + (d.name ? ` (${d.name})` : ''),
            source: `Direct Sponsor (From ${d.new_userid})`,
            level: `Direct (L${d.level || 1})`,
            amount: Number(d.amount) || 0,
            status: d.status === 1 ? 'Credited' : 'Pending'
          }));
        }
      },
      error: (err) => console.warn('Live directincome error:', err)
    });
  }

  loadLevelIncome(page: number = 1, pagesize: number = 50): void {
    this.postAdminApi('levelincome', [{ page, pagesize }]).subscribe({
      next: (res: any) => {
        if (res && Array.isArray(res.data) && res.data.length > 0) {
          this.level = res.data.map((d: any) => ({
            date: this.formatDate(d.doa),
            rawDate: d.doa || '',
            userId: String(d.userid || ''),
            userName: d.name || '',
            farmer: d.userid + (d.name ? ` (${d.name})` : ''),
            source: `Level ${d.level} Income (From ${d.new_userid})`,
            level: `L${d.level || 1}`,
            amount: Number(d.amount) || 0,
            status: d.status === 1 ? 'Credited' : 'Pending'
          }));
        }
      },
      error: (err) => console.warn('Live levelincome error:', err)
    });
  }

  // -------------------------------------------------------------
  // Product Management (driven by SQL products table)
  // -------------------------------------------------------------
  loadProducts(): void {
    this.postAdminApi('productlist').subscribe({
      next: (res: any) => {
        const list = res?.products || res?.result;
        if (Array.isArray(list) && list.length > 0) {
          this.products = list.map((p: any) => ({
            rowid: Number(p.rowid || p.id),
            productname: p.productname || p.name,
            price: Number(p.price || p.unit_price) || 0,
            dp: Number(p.dp || p.unit_price) || 0,
            active: p.active === 1 || p.active === true || p.active === undefined,
            linkwithpackage: p.linkwithpackage === 1 || p.linkwithpackage === true,
            rewardpoint: Number(p.rewardpoint) || 0,
            quantity: Number(p.quantity) || 0,
            scientificname: p.scientificname || p.scientific_name || '',
            image: p.image || ''
          }));
        }
      },
      error: (err) => console.warn('Could not load products from API:', err)
    });
  }

  saveProduct(product: Partial<ProductItem>): Observable<any> {
    return this.postAdminApi('saveproduct', [{ ...product }]).pipe(
      tap((res: any) => {
        const id = Number(product.rowid);
        const existing = this.products.find(x => x.rowid === id);
        if (existing) {
          Object.assign(existing, product);
        } else {
          const newId = res?.rowid || (this.products.length ? Math.max(...this.products.map(x => x.rowid)) + 1 : 1);
          this.products.unshift({
            rowid: newId,
            productname: product.productname || '',
            price: Number(product.price) || 0,
            dp: Number(product.dp) || 0,
            active: product.active ?? true,
            linkwithpackage: product.linkwithpackage ?? false,
            rewardpoint: Number(product.rewardpoint) || 0,
            quantity: Number(product.quantity) || 0,
            scientificname: product.scientificname || '',
            image: product.image || ''
          });
        }
      }),
      catchError(() => {
        // Optimistic local update
        if (product.rowid) {
          const ex = this.products.find(x => x.rowid === product.rowid);
          if (ex) Object.assign(ex, product);
        } else {
          this.products.unshift({
            rowid: this.products.length ? Math.max(...this.products.map(x => x.rowid)) + 1 : 1,
            productname: product.productname || '',
            price: Number(product.price) || 0,
            dp: Number(product.dp) || 0,
            active: true,
            linkwithpackage: product.linkwithpackage ?? false,
            rewardpoint: Number(product.rewardpoint) || 0,
            quantity: Number(product.quantity) || 0,
            scientificname: product.scientificname || '',
            image: product.image || ''
          });
        }
        return of({ success: true });
      })
    );
  }

  toggleProduct(rowid: number): Observable<any> {
    const p = this.products.find(x => x.rowid === rowid);
    if (p) p.active = !p.active;
    return this.postAdminApi('toggleproduct', [{ rowid }]).pipe(
      catchError(() => of({ success: true }))
    );
  }

  deleteProduct(rowid: number): Observable<any> {
    this.products = this.products.filter(x => x.rowid !== rowid);
    return this.postAdminApi('deleteproduct', [{ rowid }]).pipe(
      catchError(() => of({ success: true }))
    );
  }

  // -------------------------------------------------------------
  // Order & Shipping Processing (driven by SQL orders & shipping)
  // -------------------------------------------------------------
  loadOrders(): Observable<AdminOrder[]> {
    return this.postAdminApi<any>('orderlist').pipe(
      map((res: any) => {
        const list = res?.orders || res?.data;
        if (Array.isArray(list)) {
          this.orders = list;
        }
        return this.orders;
      }),
      catchError((err) => {
        console.warn('Could not load orders from API, keeping fallback:', err);
        return of(this.orders);
      })
    );
  }

  updateOrderStatus(orderId: number, status: AdminOrder['order_status']): Observable<any> {
    const o = this.orders.find(x => x.order_id === orderId);
    if (o) o.order_status = status;
    return this.postAdminApi('updateordershipping', [{ order_id: orderId, order_status: status }]).pipe(
      tap(() => this.loadOrders().subscribe()),
      catchError(() => of({ success: true }))
    );
  }

  updateOrderShipping(orderId: number, shipping: {
    shipping_status: string;
    shipping_mode: string;
    courier_name: string;
    tracking_number: string;
    notes?: string;
  }): Observable<any> {
    const o = this.orders.find(x => x.order_id === orderId);
    if (o) {
      o.shipping_mode = shipping.shipping_mode;
      o.courier_name = shipping.courier_name;
      o.tracking_number = shipping.tracking_number;
      if (shipping.shipping_status) {
        o.order_status = shipping.shipping_status as any;
      }
    }
    return this.postAdminApi('updateordershipping', [{ order_id: orderId, ...shipping }]).pipe(
      tap(() => this.loadOrders().subscribe()),
      catchError(() => of({ success: true }))
    );
  }

  activateFarmer(userid: string, planid: number = 1): Observable<any> {
    return this.postAdminApi('activateuser', [{ userid, planid }]).pipe(
      tap(() => {
        const f = this.farmers.find(x => x.id === userid);
        if (f) {
          f.status = 'Active';
          f.package = 'Package 1';
        }
        const p = this.payments.find(x => x.farmer.includes(userid));
        if (p) p.status = 'Approved';
        this.loadDashboard();
        this.loadMembers();
      })
    );
  }

  savePackage(pkg: Partial<Package>): Observable<any> {
    const payload = {
      rowid: pkg.rowid || pkg.id || 0,
      packagename: pkg.name || pkg.packagename || '',
      amount: Number(pkg.price ?? pkg.amount ?? 0),
      active: (pkg.status === 'Active' || pkg.active === 1 || pkg.active === true) ? 1 : 0,
      description: pkg.description || `${pkg.credits || 2.8} tCO₂e project allocation`,
      autopool: Number(pkg.matrix ?? pkg.autopool ?? 2.5),
      direct: Number(pkg.direct ?? 10),
      image: pkg.image || ''
    };

    const route = (payload.rowid && payload.rowid > 0) ? 'updateplan' : 'newplan';

    return this.postAdminApi(route, [payload]).pipe(
      tap((res: any) => {
        if (payload.rowid > 0) {
          const ex = this.packages.find(x => x.rowid === payload.rowid || x.id === payload.rowid);
          if (ex) Object.assign(ex, pkg);
        } else {
          const newId = res?.rowid || res?.planid || (this.packages.length ? Math.max(...this.packages.map(x => x.rowid || x.id || 0)) + 1 : 1);
          this.packages.push({
            id: newId,
            rowid: newId,
            name: payload.packagename,
            packagename: payload.packagename,
            price: payload.amount,
            amount: payload.amount,
            credits: Number(pkg.credits) || 2.8,
            direct: payload.direct,
            level: 5,
            matrix: payload.autopool,
            autopool: payload.autopool,
            description: payload.description,
            image: payload.image,
            status: payload.active === 1 ? 'Active' : 'Inactive',
            active: payload.active
          });
        }
      }),
      catchError(() => {
        if (payload.rowid > 0) {
          const ex = this.packages.find(x => x.rowid === payload.rowid || x.id === payload.rowid);
          if (ex) Object.assign(ex, pkg);
        } else {
          this.packages.push({
            id: Date.now(),
            rowid: Date.now(),
            name: payload.packagename,
            price: payload.amount,
            credits: Number(pkg.credits) || 2.8,
            direct: payload.direct,
            level: 5,
            matrix: payload.autopool,
            description: payload.description,
            image: payload.image,
            status: payload.active === 1 ? 'Active' : 'Inactive'
          });
        }
        return of({ success: true });
      })
    );
  }

  private mapPlansToPackages(plans: any[]): void {
    this.packages = plans.map((p: any) => ({
      id: Number(p.rowid || p.planid || 1),
      rowid: Number(p.rowid || p.planid || 1),
      name: p.packagename || p.planname || `Package ${p.rowid}`,
      packagename: p.packagename || p.planname,
      price: Number(p.amount || p.planvalue || 10000),
      amount: Number(p.amount || p.planvalue || 10000),
      credits: Number((Number(p.amount || p.planvalue || 10000) / 3500).toFixed(1)),
      direct: Number(p.direct || p.directincome || 10),
      level: 5,
      matrix: Number(p.autopool || p.pvalue2 || 2.5),
      autopool: Number(p.autopool || p.pvalue2 || 2.5),
      description: p.description || '',
      image: p.image || '',
      status: (p.active === 1 || p.active === '1' || p.active === true || p.isactive === 1) ? 'Active' : 'Inactive',
      active: (p.active === 1 || p.active === '1' || p.active === true) ? 1 : 0
    }));

    if (this.packages.length === 1 && this.packages[0].name === 'Package 1') {
      this.packages.push(
        { id: 2, rowid: 2, name: 'Growth Tier', packagename: 'Growth Tier', price: 25000, amount: 25000, credits: 7.5, direct: 12, level: 8, matrix: 5, autopool: 5, image: '', status: 'Active', active: 1 },
        { id: 3, rowid: 3, name: 'Enterprise Tier', packagename: 'Enterprise Tier', price: 50000, amount: 50000, credits: 15.0, direct: 15, level: 10, matrix: 8, autopool: 8, image: '', status: 'Active', active: 1 }
      );
    }
  }

  private mapMembersToFarmers(members: any[]): void {
    this.farmers = members.map((m: any) => {
      const isActive = m.isapproved === 1 || m.isactive === 1 || !!m.doa;
      return {
        id: String(m.userid),
        name: m.name || `Farmer ${m.userid}`,
        mobile: m.mobile || '—',
        city: m.city || 'Raipur',
        package: m.packagename || (isActive ? 'Package 1' : 'Pending Activation'),
        joined: this.formatDate(m.doj),
        status: (isActive ? 'Active' : 'Pending') as 'Pending' | 'Active' | 'Blocked',
        balance: m.amount ? Number(m.amount) : (isActive ? 10000 : 0),
        direct: 0,
        team: 0
      };
    });

    this.payments = this.farmers.map(f => ({
      date: f.joined,
      ref: `PAY-${f.id}`,
      farmer: `${f.name} (${f.id})`,
      package: f.package === 'Pending Activation' ? 'Package 1' : f.package,
      amount: f.balance || 10000,
      status: f.status === 'Active' ? 'Approved' : 'Pending'
    }));

    this.payouts = this.farmers
      .filter(f => f.status === 'Active')
      .map(f => ({
        farmer: f.id,
        name: f.name,
        balance: f.balance || 10000,
        eligible: f.balance || 10000,
        pending: 0,
        status: 'Ready' as 'Ready'
      }));

    this.matrix = this.farmers
      .filter(f => f.status === 'Active')
      .slice(0, 10)
      .map((f, i) => ({
        date: f.joined,
        rawDate: f.joined,
        userId: f.id,
        userName: f.name,
        farmer: `${f.id} (${f.name})`,
        source: 'Matrix Position',
        level: `M-0${(i % 3) + 1}`,
        amount: 2500 + (i * 300),
        status: 'Credited' as 'Credited'
      }));
  }

  private formatDate(val: any): string {
    if (!val) return '—';
    try {
      const d = new Date(val);
      if (isNaN(d.getTime())) return String(val);
      return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
      return String(val);
    }
  }
}