import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService, Package } from '../../core/data.service';
import { PageHeaderComponent } from '../../shared/page-header.component';

@Component({
  selector: 'app-packages',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  templateUrl: './packages.component.html',
  styleUrl: './packages.component.scss'
})
export class PackagesComponent {
  showModal = false;
  isEdit = false;
  editing: Partial<Package> = {};
  uploading = false;
  uploadError = '';
  uploadSuccess = '';

  constructor(public d: DataService) {}

  toggle(p: Package): void {
    p.status = p.status === 'Active' ? 'Inactive' : 'Active';
    this.d.savePackage(p).subscribe();
  }

  openCreate(): void {
    this.isEdit = false;
    this.editing = {
      name: '',
      price: 10000,
      credits: 2.8,
      direct: 10,
      level: 5,
      matrix: 2.5,
      image: '',
      status: 'Active'
    };
    this.uploadError = '';
    this.uploadSuccess = '';
    this.showModal = true;
  }

  openEdit(p: Package): void {
    this.isEdit = true;
    this.editing = { ...p };
    this.uploadError = '';
    this.uploadSuccess = '';
    this.showModal = true;
  }

  closeModal(): void {
    this.showModal = false;
    this.editing = {};
    this.uploadError = '';
    this.uploadSuccess = '';
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];

    if (!file.type.startsWith('image/')) {
      this.uploadError = 'Please select an image file (JPG, PNG, WebP).';
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      this.uploadError = 'File size exceeds 10MB limit.';
      return;
    }

    this.uploading = true;
    this.uploadError = '';
    this.uploadSuccess = '';

    const reader = new FileReader();
    reader.onload = () => {
      this.editing.image = reader.result as string;
    };
    reader.readAsDataURL(file);

    this.d.uploadImage(file, 'packages').subscribe({
      next: (res) => {
        this.uploading = false;
        if (res && res.result === 1 && (res.url || res.filepath)) {
          this.editing.image = res.url || `https://www.carbonovaworld.com/${res.filepath}`;
          this.uploadSuccess = '✓ Package image uploaded to server!';
        } else {
          this.uploadError = res?.message || 'Failed to upload image.';
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
    this.editing.image = '';
    this.uploadSuccess = '';
    this.uploadError = '';
  }

  save(): void {
    if (!this.editing.name?.trim()) return;
    this.d.savePackage(this.editing).subscribe({
      next: () => this.closeModal(),
      error: () => this.closeModal()
    });
  }
}