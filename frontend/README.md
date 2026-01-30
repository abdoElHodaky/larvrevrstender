# Frontend Directory

This directory will contain the frontend applications for the Reverse Tender Platform.

## Planned Applications

### PWA (Progressive Web App) - Customer Interface
- **Location**: `frontend/pwa/`
- **Technology**: Vue.js 3 + PWA
- **Port**: 3000
- **Purpose**: Customer-facing application for submitting part requests and viewing bids

**Features**:
- Part request submission with photos
- Real-time bid notifications
- Merchant discovery and ratings
- Order tracking and history
- Mobile-optimized interface
- Offline capability

### Admin Dashboard - Administrative Interface  
- **Location**: `frontend/admin/`
- **Technology**: Vue.js 3 + Admin UI
- **Port**: 3001
- **Purpose**: Administrative interface for platform management

**Features**:
- User management (customers + merchants)
- Order and bid monitoring
- Analytics and reporting
- System configuration
- Content management

## Development Status

🚧 **Phase 0: Foundation Setup** (Current)
- Docker orchestration complete
- Frontend infrastructure planned
- CI/CD pipeline configured

⏳ **Phase 3: PWA Frontend Development** (Upcoming)
- Vue.js 3 setup with PWA capabilities
- Customer interface implementation
- Real-time WebSocket integration
- Mobile-responsive design

⏳ **Phase 4: Admin Dashboard** (Upcoming)
- Administrative interface development
- Analytics dashboard
- User management interface
- System monitoring tools

## Technology Stack

- **Framework**: Vue.js 3 with Composition API
- **Build Tool**: Vite
- **UI Library**: Vuetify 3 / Tailwind CSS
- **State Management**: Pinia
- **PWA**: Workbox for service workers
- **Testing**: Vitest + Vue Test Utils
- **Linting**: ESLint + Prettier

## Getting Started

Each frontend application will have:
- `package.json` for dependencies
- `vite.config.js` for build configuration
- `src/` directory with Vue components
- `public/` directory for static assets
- Unit and integration tests
- PWA manifest and service worker (for PWA)

Applications will communicate with backend services through the API Gateway at port 8000.
