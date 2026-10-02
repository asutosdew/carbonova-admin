import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
@Component({selector:'app-edit-modal',standalone:true,imports:[CommonModule,FormsModule],templateUrl:'./edit-modal.component.html',styleUrl:'./edit-modal.component.scss'})
export class EditModalComponent{
 @Input() title='Edit'; @Input() model:any={}; @Input() fields:any[]=[];
 @Output() saved=new EventEmitter<any>(); @Output() closed=new EventEmitter<void>();
}
