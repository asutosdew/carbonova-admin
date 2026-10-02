# Carbon Farming Admin Panel

Angular 20 standalone admin console following the same single visual direction as the Carbon Farming farmer client panel.

## Modules
- Dashboard
- Member approval / member management
- Packages
- Payment management
- Direct income
- Level income
- Matrix income
- Farmer payout summary
- Farmer accounts
- System transactions
- Settings
- Admin login

## Run
npm install
npm start

The current data is intentionally mock data. Replace `DataService` and `AuthService` with the PHP API integration layer. The UI direction is intentionally not diversified: every screen uses the same Carbon Farming green / cream / gold system.


## Multi-user / RBAC
Default roles: Super Admin, Operations Admin, Finance Admin, Support Admin and Viewer. Admin Users can be added, activated/deactivated and assigned roles. Sidebar navigation is permission-aware. For production, replace the mock AuthService arrays with PHP/MySQL API responses.

## Table Pagination
All admin list tables now use the shared `PaginationComponent`. Default page size is 10 rows with options 10/25/50, previous/next controls, page numbers, and a mobile-friendly layout.
