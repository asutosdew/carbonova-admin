import {Injectable} from '@angular/core';
export interface Farmer{ id:string;name:string;mobile:string;city:string;package:string;joined:string;status:'Pending'|'Active'|'Blocked';balance:number;direct:number;team:number; }
export interface Income{date:string;farmer:string;source:string;level:string;amount:number;status:'Credited'|'Pending';}
@Injectable({providedIn:'root'}) export class DataService{
 farmers:Farmer[]=[
 {id:'CF10421',name:'Rakesh Patel',mobile:'98XXXX1201',city:'Raipur',package:'Growth',joined:'09 Aug 2026',status:'Pending',balance:12400,direct:7,team:31},
 {id:'CF10318',name:'Meena Sahu',mobile:'97XXXX4408',city:'Bilaspur',package:'Professional',joined:'07 Aug 2026',status:'Active',balance:28600,direct:5,team:18},
 {id:'CF10174',name:'Sanjay Verma',mobile:'91XXXX5520',city:'Durg',package:'Growth',joined:'03 Aug 2026',status:'Active',balance:17250,direct:8,team:26},
 {id:'CF09961',name:'Kavita Yadav',mobile:'88XXXX9012',city:'Korba',package:'Starter',joined:'28 Jul 2026',status:'Pending',balance:5300,direct:3,team:9},
 {id:'CF09812',name:'Amit Kumar',mobile:'90XXXX3344',city:'Raigarh',package:'Professional',joined:'24 Jul 2026',status:'Active',balance:22100,direct:4,team:16}];
 level:Income[]=[
 {date:'09 Aug 2026',farmer:'CF10421',source:'Level 1',level:'L1',amount:4200,status:'Credited'},
 {date:'08 Aug 2026',farmer:'CF10318',source:'Level 2',level:'L2',amount:2600,status:'Credited'},
 {date:'06 Aug 2026',farmer:'CF10174',source:'Level 3',level:'L3',amount:1850,status:'Credited'},
 {date:'04 Aug 2026',farmer:'CF09961',source:'Level 4',level:'L4',amount:1400,status:'Pending'}];
 matrix:Income[]=[
 {date:'09 Aug 2026',farmer:'CF10421',source:'Matrix Position',level:'M-03',amount:3500,status:'Credited'},
 {date:'07 Aug 2026',farmer:'CF10318',source:'Matrix Position',level:'M-02',amount:2800,status:'Credited'},
 {date:'05 Aug 2026',farmer:'CF10174',source:'Matrix Position',level:'M-01',amount:2400,status:'Credited'}];
 direct:Income[]=[
 {date:'09 Aug 2026',farmer:'CF10421',source:'Direct Farmer',level:'Direct',amount:4800,status:'Credited'},
 {date:'07 Aug 2026',farmer:'CF10318',source:'Direct Farmer',level:'Direct',amount:3200,status:'Credited'},
 {date:'03 Aug 2026',farmer:'CF10174',source:'Direct Farmer',level:'Direct',amount:2500,status:'Credited'}];
 packages=[{name:'Starter',price:5000,credits:1.2,direct:10,level:5,matrix:5,status:'Active'},{name:'Growth',price:10000,credits:2.8,direct:12,level:8,matrix:8,status:'Active'},{name:'Professional',price:25000,credits:7.5,direct:15,level:10,matrix:10,status:'Active'}];
 payments=[{date:'09 Aug 2026',ref:'PAY-80421',farmer:'CF10421',package:'Growth',amount:10000,status:'Pending'},{date:'08 Aug 2026',ref:'PAY-80318',farmer:'CF10318',package:'Professional',amount:25000,status:'Approved'},{date:'07 Aug 2026',ref:'PAY-80174',farmer:'CF10174',package:'Growth',amount:10000,status:'Approved'}];
 payouts=[{farmer:'CF10421',name:'Rakesh Patel',balance:12400,eligible:12400,pending:0,status:'Ready'},{farmer:'CF10318',name:'Meena Sahu',balance:28600,eligible:26000,pending:2600,status:'Review'},{farmer:'CF10174',name:'Sanjay Verma',balance:17250,eligible:17250,pending:0,status:'Ready'},{farmer:'CF09961',name:'Kavita Yadav',balance:5300,eligible:5300,pending:0,status:'Ready'}];
}