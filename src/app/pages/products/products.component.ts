import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService, ProductItem } from '../../core/data.service';

@Component({
  selector: 'app-products',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './products.component.html',
  styleUrl: './products.component.scss'
})
export class ProductsComponent implements OnInit {
  showForm = false;
  showConfirm = false;
  editingProduct: ProductItem | null = null;
  pendingAction: 'save' | 'toggle' | 'delete' | null = null;
  pendingProduct: ProductItem | null = null;

  form: ProductItem = this.emptyProduct();
  search = '';
  statusFilter = 'All';
  packageFilter = 'All';

  uploading = false;
  uploadError = '';
  uploadSuccess = '';

  constructor(public d: DataService) {}

  ngOnInit(): void {
    this.d.loadProducts();
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];

    if (!file.type.startsWith('image/')) {
      this.uploadError = 'Please select a valid image file (JPG, PNG, WebP).';
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      this.uploadError = 'File size exceeds 10MB limit.';
      return;
    }

    this.uploading = true;
    this.uploadError = '';
    this.uploadSuccess = '';

    // Show preview immediately
    const reader = new FileReader();
    reader.onload = () => {
      this.form.image = reader.result as string;
    };
    reader.readAsDataURL(file);

    // Send to server
    this.d.uploadImage(file, 'products').subscribe({
      next: (res) => {
        this.uploading = false;
        if (res && res.result === 1 && (res.url || res.filepath)) {
          this.form.image = res.url || `https://www.carbonovaworld.com/${res.filepath}`;
          this.uploadSuccess = '✓ Photo uploaded to server successfully!';
        } else {
          this.uploadError = res?.message || 'Upload failed on server.';
        }
      },
      error: (err) => {
        this.uploading = false;
        console.warn('Image upload error:', err);
        this.uploadError = 'Could not upload to server. Check connection.';
      }
    });
  }

  removeImage(): void {
    this.form.image = '';
    this.uploadSuccess = '';
    this.uploadError = '';
  }

  emptyProduct(): ProductItem {
    return {
      rowid: 0,
      productname: '',
      price: 0,
      dp: 0,
      active: true,
      linkwithpackage: false,
      rewardpoint: 0,
      quantity: 50,
      scientificname: '',
      image: ''
    };
  }

  get filteredProducts(): ProductItem[] {
    const q = this.search.trim().toLowerCase();
    return this.d.products.filter(p => {
      const matchesSearch = !q ||
        p.productname.toLowerCase().includes(q) ||
        (p.scientificname && p.scientificname.toLowerCase().includes(q)) ||
        String(p.rowid).includes(q);

      const isActive = p.active === true || p.active === 1;
      const matchesStatus = this.statusFilter === 'All' ||
        (this.statusFilter === 'Active' && isActive) ||
        (this.statusFilter === 'Inactive' && !isActive);

      const isPackageLinked = p.linkwithpackage === true || p.linkwithpackage === 1;
      const matchesPackage = this.packageFilter === 'All' ||
        (this.packageFilter === 'Package Linked' && isPackageLinked) ||
        (this.packageFilter === 'Standalone Only' && !isPackageLinked);

      return matchesSearch && matchesStatus && matchesPackage;
    });
  }

  get activeCount(): number {
    return this.d.products.filter(p => p.active === true || p.active === 1).length;
  }

  get inactiveCount(): number {
    return this.d.products.filter(p => p.active === false || p.active === 0).length;
  }

  get packageLinkedCount(): number {
    return this.d.products.filter(p => p.linkwithpackage === true || p.linkwithpackage === 1).length;
  }

  openAdd(): void {
    this.editingProduct = null;
    this.form = this.emptyProduct();
    this.showForm = true;
  }

  openEdit(p: ProductItem): void {
    this.editingProduct = p;
    this.form = { ...p };
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.editingProduct = null;
    this.form = this.emptyProduct();
  }

  askSave(): void {
    if (!this.form.productname.trim() || this.form.price <= 0) return;
    this.pendingAction = 'save';
    this.pendingProduct = { ...this.form };
    this.showConfirm = true;
  }

  askToggle(p: ProductItem): void {
    this.pendingAction = 'toggle';
    this.pendingProduct = p;
    this.showConfirm = true;
  }

  askDelete(p: ProductItem): void {
    this.pendingAction = 'delete';
    this.pendingProduct = p;
    this.showConfirm = true;
  }

  confirmAction(): void {
    if (!this.pendingProduct || !this.pendingAction) return;

    if (this.pendingAction === 'save') {
      this.d.saveProduct(this.pendingProduct).subscribe();
      this.closeForm();
    } else if (this.pendingAction === 'toggle') {
      this.d.toggleProduct(this.pendingProduct.rowid).subscribe();
    } else if (this.pendingAction === 'delete') {
      this.d.deleteProduct(this.pendingProduct.rowid).subscribe();
    }
    this.closeConfirm();
  }

  closeConfirm(): void {
    this.showConfirm = false;
    this.pendingAction = null;
    this.pendingProduct = null;
  }

  get confirmationTitle(): string {
    if (this.pendingAction === 'toggle') {
      return (this.pendingProduct?.active === true || this.pendingProduct?.active === 1)
        ? 'Deactivate Product?'
        : 'Activate Product?';
    }
    if (this.pendingAction === 'delete') {
      return 'Delete Product?';
    }
    return this.editingProduct ? 'Confirm Product Update' : 'Confirm New Product';
  }

  get confirmationMessage(): string {
    if (this.pendingAction === 'toggle') {
      return (this.pendingProduct?.active === true || this.pendingProduct?.active === 1)
        ? 'This plant/product will become inactive and hidden from customer store & packages.'
        : 'This plant/product will become active for packages and customer store selections.';
    }
    if (this.pendingAction === 'delete') {
      return `Are you sure you want to delete "${this.pendingProduct?.productname}" from the catalogue?`;
    }
    return this.editingProduct
      ? 'Confirm the updated product details before syncing with the database.'
      : 'Confirm you want to add this plant/product with its reward points and package linkage.';
  }

  get confirmationButton(): string {
    if (this.pendingAction === 'toggle') {
      return (this.pendingProduct?.active === true || this.pendingProduct?.active === 1)
        ? 'Deactivate'
        : 'Activate';
    }
    if (this.pendingAction === 'delete') {
      return 'Delete Permanently';
    }
    return this.editingProduct ? 'Save Changes' : 'Add Product';
  }
}
