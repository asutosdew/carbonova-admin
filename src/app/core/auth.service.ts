import { Injectable } from '@angular/core';

export type RoleName = 'Super Admin' | 'Operations Admin' | 'Finance Admin' | 'Support Admin' | 'Viewer';

export interface AdminUser {
  id: number; name: string; username: string; email: string;
  role: RoleName; status: 'Active' | 'Inactive';
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly loginKey = 'cf_admin_logged';
  private readonly userKey = 'cf_admin_user';

  private users: AdminUser[] = [
    {id:1,name:'System Administrator',username:'superadmin',email:'admin@carbonfarm.local',role:'Super Admin',status:'Active'},
    {id:2,name:'Operations Manager',username:'operations',email:'operations@carbonfarm.local',role:'Operations Admin',status:'Active'},
    {id:3,name:'Finance Manager',username:'finance',email:'finance@carbonfarm.local',role:'Finance Admin',status:'Active'},
    {id:4,name:'Support Executive',username:'support',email:'support@carbonfarm.local',role:'Support Admin',status:'Active'},
    {id:5,name:'Reporting User',username:'viewer',email:'viewer@carbonfarm.local',role:'Viewer',status:'Active'}
  ];

  private permissions: Record<RoleName,string[]> = {
    'Super Admin':['dashboard','members','packages','payments','direct-income','level-income','matrix-income','payouts','accounts','transactions','users-roles','settings'],
    'Operations Admin':['dashboard','members','packages','direct-income','level-income','matrix-income','accounts','transactions'],
    'Finance Admin':['dashboard','payments','payouts','accounts','transactions'],
    'Support Admin':['dashboard','members','accounts','transactions'],
    'Viewer':['dashboard','members','packages','payments','direct-income','level-income','matrix-income','payouts','accounts','transactions']
  };

  login(username:string,password:string):boolean {
    const user=this.users.find(x=>x.username===username.trim() && x.status==='Active');
    if(user && password.trim()){localStorage.setItem(this.loginKey,'1');localStorage.setItem(this.userKey,JSON.stringify(user));return true;}
    return false;
  }
  isLoggedIn(){return localStorage.getItem(this.loginKey)==='1';}
  logout(){localStorage.removeItem(this.loginKey);localStorage.removeItem(this.userKey);}
  currentUser():AdminUser {
    try{return JSON.parse(localStorage.getItem(this.userKey)||'') as AdminUser;}catch{return this.users[0];}
  }
  hasPermission(permission:string){return this.permissions[this.currentUser().role]?.includes(permission)??false;}
  isSuperAdmin(){return this.currentUser().role==='Super Admin';}
  getUsers(){return this.users;}
  getRoles(){return (Object.keys(this.permissions) as RoleName[]).map(name=>({
    name,
    description:({'Super Admin':'Full system control','Operations Admin':'Farmer and network operations','Finance Admin':'Payments, payouts and accounts','Support Admin':'Farmer support and account lookup','Viewer':'Read-only reporting access'} as Record<RoleName,string>)[name],
    permissions:this.permissions[name]
  }));}
  addUser(user:Omit<AdminUser,'id'>){this.users.push({...user,id:Date.now()});}
  toggleUser(user:AdminUser){user.status=user.status==='Active'?'Inactive':'Active';}
}