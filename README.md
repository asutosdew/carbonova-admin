# Carbonova World Admin Panel

Angular 20 standalone admin console following the same single visual direction as the Carbonova World farmer client panel.

## Modules
- Dashboard
- Member approval / member management
- Packages / Plans
- Payment management
- Plants & Products catalogue
- Customer orders & shipping logistics
- Direct income
- Level income
- Matrix income
- Farmer payout summary
- Farmer accounts
- System transactions
- Admin users & role permissions
- Settings
- Admin login

## Run
```bash
npm install
npm start
```

## Multi-user / RBAC
Default roles: Super Admin, Operations Admin, Finance Admin, Support Admin and Viewer. Admin Users can be added, activated/deactivated and assigned roles. Sidebar navigation is permission-aware.

## Table Pagination
All admin list tables use the shared `PaginationComponent`. Default page size is 10 rows with options 10/25/50, previous/next controls, page numbers, and a mobile-friendly layout.
