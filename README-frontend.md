# FunaGig Frontend Documentation

## Overview
The FunaGig frontend is built with vanilla HTML, CSS, and JavaScript, designed for easy deployment on XAMPP. It features a responsive, Fiverr-inspired design with role-based access for students and businesses.

## File Structure
```
funagig/
├── css/
│   └── styles.css          # Global styles (responsive, Fiverr-inspired)
├── js/
│   ├── app.js              # Core JS (shared utilities, API calls, localStorage)
│   ├── messaging.js        # Messaging logic (for student/business-messaging.html)
│   └── dashboard.js        # Dashboard-specific (stats, dynamic loads)
├── index.html              # Landing page
├── home-gigs.html          # Visitor gig browse
├── auth.html               # Login (unified for student/business)
├── signup.html             # Signup (unified with role selector)
├── student-dashboard.html  # Student dashboard
├── student-profile.html    # Student profile management
├── student-messaging.html  # Student messaging interface
├── student-gigs.html       # Student gig browsing and applications
├── business-dashboard.html # Business dashboard
├── business-profile.html   # Business profile management
├── business-messaging.html # Business messaging interface
└── business-gigs.html     # Business gig management (post/posted/applicants)
```

## Key Features

### Responsive Design
- Mobile-first approach
- Fiverr-inspired modern UI
- Consistent color scheme and typography
- Grid-based layouts with CSS Grid and Flexbox

### Role-Based Access
- **Students**: Browse gigs, apply, track applications, manage profile
- **Businesses**: Post gigs, manage applications, hire students, analytics
- **Visitors**: Browse public gigs, learn about platform

### Navigation
- Consistent header across all pages
- Role-specific sidebar navigation for authenticated users
- Breadcrumb navigation for complex workflows

## CSS Architecture

### Global Styles (`css/styles.css`)
- CSS Custom Properties for consistent theming
- Utility classes for spacing, colors, and layout
- Component-based styling (buttons, cards, forms)
- Responsive breakpoints

### Key CSS Classes
```css
/* Layout */
.container          # Main content wrapper
.app-layout        # Dashboard layout with sidebar
.section           # Content sections
.grid-2, .grid-3, .grid-4  # Grid layouts

/* Components */
.btn               # Primary buttons
.btn.secondary     # Secondary buttons
.btn.ghost         # Ghost buttons
.card              # Content cards
.form              # Form styling

/* Utilities */
.mt-20, .mb-10     # Spacing utilities
.flex, .items-center  # Flexbox utilities
.text-right        # Text alignment
```

## JavaScript Architecture

### Core Module (`js/app.js`)
- **API Integration**: Centralized API calls with error handling
- **Authentication**: User session management
- **Storage**: LocalStorage utilities
- **Validation**: Form validation helpers
- **UI Utilities**: Common UI functions

### Messaging Module (`js/messaging.js`)
- Real-time messaging functionality
- Conversation management
- Message threading
- Notification system

### Dashboard Module (`js/dashboard.js`)
- Dynamic stats loading
- Chart rendering
- Quick actions
- Analytics display

## Page-Specific Features

### Landing Page (`index.html`)
- Hero section with call-to-action
- Feature highlights
- About section
- How it works
- Contact form
- FAQ section

### Authentication (`auth.html`, `signup.html`)
- Unified login/signup forms
- Role selection
- Form validation
- Social login options (placeholder)

### Student Pages
- **Dashboard**: Stats, recent activity, quick actions
- **Profile**: Skills, education, portfolio
- **Gigs**: Browse, filter, apply to gigs
- **Messaging**: Chat with businesses

### Business Pages
- **Dashboard**: Analytics, active gigs, applicants
- **Profile**: Company info, industry, location
- **Gigs**: Post new gigs, manage existing
- **Messaging**: Chat with students

## API Integration

### Endpoints Used
```javascript
// Authentication
POST /php/api.php/login
POST /php/api.php/signup
POST /php/api.php/logout

// Dashboard
GET /php/api.php/dashboard

// Gigs
GET /php/api.php/gigs
POST /php/api.php/gigs
GET /php/api.php/gigs/active

// Applications
POST /php/api.php/applications

// Messaging
GET /php/api.php/conversations
POST /php/api.php/conversations
GET /php/api.php/messages/{id}
POST /php/api.php/messages
```

### Error Handling
- Global error handling in `app.js`
- User-friendly error messages
- Network error fallbacks
- Loading states

## Browser Support
- Modern browsers (Chrome, Firefox, Safari, Edge)
- ES6+ features with fallbacks
- CSS Grid and Flexbox support
- LocalStorage for session management

## Performance Optimizations
- Minified CSS and JS in production
- Lazy loading for images
- Efficient DOM manipulation
- Cached API responses

## Development Guidelines

### Adding New Pages
1. Create HTML file in root directory
2. Include `css/styles.css` and `js/app.js`
3. Add page-specific JS if needed
4. Update navigation links
5. Test responsive design

### Styling Guidelines
- Use CSS custom properties for colors
- Follow BEM methodology for complex components
- Mobile-first responsive design
- Consistent spacing using utility classes

### JavaScript Guidelines
- Use modern ES6+ features
- Modular approach with separate files
- Error handling for all API calls
- Consistent naming conventions

