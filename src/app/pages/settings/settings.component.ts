import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { PageHeaderComponent } from '../../shared/page-header.component';

@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [FormsModule, PageHeaderComponent],
  templateUrl: './settings.component.html',
  styleUrl: './settings.component.scss'
})
export class SettingsComponent {
  company = 'Carbonova World';
  email = 'support@carbonovaworld.com';
  approval = true;
}