
# Inertia.js PHP Adapter for TinyMVC

A server-side adapter for [Inertia.js](https://inertiajs.com) v2, enabling you to build modern single-page applications using classic server-side routing and controllers.

Inertia eliminates the complexity of building SPAs. There's no need for client-side routing, API endpoints, or data fetching logic. Simply build controllers and page views like you've always done.

## Installation

```bash
composer require tinymvc/inertia-php
```

## Setup

Register the service provider in your application:

```php
// bootstrap/providers.php
return [
    \Inertia\InertiaServiceProvider::class,
];
```

The service provider automatically registers:
- The `Inertia` singleton
- The `@inertia` Blade directive
- The `Route::inertia()` router macro

## Basic Usage

There are multiple ways to create Inertia responses:

### Using the Inertia Class

```php
use Inertia\Facades\Inertia;

class UsersController
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::all(),
        ]);
    }
}
```

### Using the Helper Function

The `inertia()` helper provides a convenient shorthand:

```php
class UsersController
{
    public function index()
    {
        // Render a component with props
        return inertia('Users/Index', [
            'users' => User::all(),
        ]);
    }
    
    public function show(User $user)
    {
        // Access Inertia instance without rendering
        $inertia = inertia();
        $inertia->setRootView('layouts.admin');
        
        return $inertia->render('Users/Show', [
            'user' => $user,
        ]);
    }
}
```

### Using Route Macro

For simple pages that don't require a controller, use the `Route::inertia()` macro:

```php
// routes/web.php
use Spark\Facades\Route;

Route::inertia('/', 'Home');
Route::inertia('/about', 'About', ['version' => '1.0.0']);
Route::inertia('/contact', 'Contact');
```

This is equivalent to:

```php
Route::get('/', fn() => inertia('Home'));
```

## Root Template

Create a root Blade template that will load your JavaScript application:

```blade
<!-- resources/views/app.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My App</title>
    @vite('app.tsx')
</head>
<body>
    @inertia
</body>
</html>
```

The `@inertia` Blade directive renders a `<div>` element with a `data-page` attribute containing the page data:

```html
<div id="app" data-page="{&quot;component&quot;:&quot;Home&quot;,&quot;props&quot;:{...}}"></div>
```

### Custom Root View

By default, Inertia uses the `app` view. To change it:

```php
Inertia::setRootView('layouts.admin');
```

## Sharing Data

Share data globally across all Inertia responses. This is useful for data like the authenticated user, flash messages, or application settings.

```php
// In a middleware or service provider
use Inertia\Facades\Inertia;

// Share a single value
Inertia::share('appName', config('app.name'));

// Share multiple values
Inertia::share([
    'auth' => [
        'user' => auth()->user(),
    ],
    'flash' => [
        'success' => session('success'),
        'error' => session('error'),
    ],
    'locale' => app()->getLocale(),
]);

// Retrieve shared data
$appName = Inertia::getShared('appName');
$allShared = Inertia::getShared(); // Returns all shared data
```

## Props

Props are the data passed to your page components. Inertia supports various prop types for different use cases.

### Standard Props

Regular props are always included in responses:

```php
return inertia('Users/Index', [
    'users' => User::all(),
    'filters' => $request->only(['search', 'status']),
]);
```

### Lazy Evaluation with Closures

Wrap props in closures for lazy evaluation. The closure is only executed when the prop is actually needed:

```php
return inertia('Dashboard', [
    // Always evaluated
    'user' => $user,
    
    // Only evaluated when included in response
    'users' => fn () => User::all(),
    'companies' => fn () => Company::all(),
]);
```

### Optional Props

Optional props are never included in the initial response. They must be explicitly requested via partial reloads:

```php
return inertia('Reports', [
    'summary' => $summary,
    
    // Only loaded when explicitly requested
    'detailedReport' => Inertia::optional(fn () => Report::generateDetailed()),
]);
```

Client-side request:
```js
router.reload({ only: ['detailedReport'] })
```

### Always Props

Always props are included in every response, even during partial reloads when not explicitly requested:

```php
return inertia('Dashboard', [
    'users' => fn () => User::all(),
    
    // Always fresh, even in partial reloads
    'notifications' => Inertia::always(fn () => auth()->user()->unreadNotifications),
    'flash' => Inertia::always(fn () => session('flash')),
]);
```

## Deferred Props

Deferred props are excluded from the initial page load and loaded in a subsequent request after the page renders. This improves perceived performance for pages with expensive data.

```php
return inertia('Dashboard', [
    // Loaded immediately
    'user' => $user,
    
    // Loaded after page renders
    'stats' => Inertia::defer(fn () => Stats::calculate()),
    'recentActivity' => Inertia::defer(fn () => Activity::recent()),
]);
```

### Grouping Deferred Props

Group related deferred props to load them in a single request:

```php
return inertia('Dashboard', [
    // Default group - separate request
    'permissions' => Inertia::defer(fn () => Permission::all()),
    
    // 'sidebar' group - loaded together in one request
    'teams' => Inertia::defer(fn () => Team::all(), 'sidebar'),
    'projects' => Inertia::defer(fn () => Project::all(), 'sidebar'),
    
    // 'charts' group - another separate request
    'salesChart' => Inertia::defer(fn () => Chart::sales(), 'charts'),
    'trafficChart' => Inertia::defer(fn () => Chart::traffic(), 'charts'),
]);
```

## Merge Props

Merge props append or prepend data to existing client-side data instead of replacing it. Perfect for infinite scrolling, real-time feeds, and pagination.

### Append (Default)

```php
return inertia('Feed', [
    // New items are appended to existing items
    'posts' => Inertia::merge(fn () => Post::paginate(10)),
]);
```

### Prepend

```php
return inertia('Chat', [
    // New messages appear at the top
    'messages' => Inertia::prepend(fn () => Message::latest()->take(20)->get()),
]);
```

### Deep Merge

For nested objects where you want to merge at all levels:

```php
return inertia('Settings', [
    'config' => Inertia::deepMerge(fn () => [
        'notifications' => ['email' => true],
        'privacy' => ['showProfile' => false],
    ]),
]);
```

### Deduplication

Prevent duplicate items when merging by specifying a match key:

```php
return inertia('Feed', [
    // Match by 'id' field to avoid duplicates
    'posts' => Inertia::merge(fn () => Post::paginate())->matchOn('id'),
]);
```

## Once Props

Once props are evaluated and sent only once. On subsequent page visits, the client reuses the cached value, reducing server load for static or rarely-changing data.

```php
return inertia('Settings', [
    // Fetched once, then cached on client
    'countries' => Inertia::once(fn () => Country::all()),
    'timezones' => Inertia::once(fn () => Timezone::all()),
]);
```

### Expiration

Set an expiration time after which the prop will be re-fetched:

```php
return inertia('Dashboard', [
    // Refresh after 1 day
    'exchangeRates' => Inertia::once(fn () => ExchangeRate::all())
        ->until(now()->addDay()),
    
    // Refresh after 1 hour (using seconds)
    'weather' => Inertia::once(fn () => Weather::current())
        ->until(3600),
]);
```

### Custom Keys

Share once props across different pages using custom keys:

```php
// On Team/Index page
return inertia('Team/Index', [
    'memberRoles' => Inertia::once(fn () => Role::all())->as('roles'),
]);

// On Team/Invite page - reuses the same cached data
return inertia('Team/Invite', [
    'availableRoles' => Inertia::once(fn () => Role::all())->as('roles'),
]);
```

### Force Refresh

Force a prop to be re-fetched even if cached:

```php
return inertia('Billing', [
    'plans' => Inertia::once(fn () => Plan::all())->fresh(),
    
    // Conditional refresh
    'features' => Inertia::once(fn () => Feature::all())->fresh($planChanged),
]);
```

### Global Once Props

Share once props across all pages:

```php
// In a service provider or middleware
Inertia::shareOnce('countries', fn () => Country::all());
Inertia::shareOnce('config', fn () => AppConfig::all())->until(now()->addHour());
```

## Chaining Modifiers

Combine prop types for advanced behavior:

```php
return inertia('Dashboard', [
    // Load after render, then cache
    'stats' => Inertia::defer(fn () => Stats::generate())->once(),
    
    // Merge new data, then cache
    'activity' => Inertia::merge(fn () => $user->recentActivity())->once(),
    
    // Only load when requested, then cache
    'reports' => Inertia::optional(fn () => Report::all())->once(),
    
    // Defer loading, then merge with existing data
    'notifications' => Inertia::defer(fn () => Notification::paginate())->merge(),
]);
```

## History Encryption

Encrypt the page data stored in browser history to protect sensitive information after logout.

### Per-Request Encryption

```php
// Enable encryption for this response
return inertia()
    ->withEncryptedHistory()
    ->render('Account/Settings', $props);
```

### Clear History

Clear previously encrypted history entries (useful after logout):

```php
return inertia()
    ->withClearedHistory()
    ->render('Auth/Login');
```

## Redirects

Inertia handles redirects intelligently, maintaining SPA behavior.

### Standard Redirects

```php
public function store(Request $request)
{
    User::create($request->validated());
    
    return inertia()->redirect('/users');
}
```

### Back Redirect

```php
public function update(Request $request, User $user)
{
    $user->update($request->validated());
    
    return inertia()->back();
}
```

### External Redirects

For redirects to external URLs or non-Inertia pages:

```php
return inertia()->location('https://external-site.com');
return inertia()->location('/non-inertia-page');
```

> **Note:** Inertia automatically uses 303 status codes for redirects after PUT, PATCH, or DELETE requests to prevent browser confirmation dialogs.

## View Composers

View composers run before specific components are rendered, allowing you to attach data dynamically.

```php
// Single component
Inertia::composer('Dashboard', function ($inertia) {
    Inertia::share([
        'dashboardStats' => Stats::forDashboard(),
    ]);
});

// Multiple components
Inertia::composer(['Users/Index', 'Users/Show', 'Users/Edit'], function ($inertia) {
    Inertia::share([
        'roles' => Role::all(),
        'departments' => Department::all(),
    ]);
});

// All components (useful for global data)
Inertia::composer('*', function ($inertia) {
    Inertia::share([
        'appVersion' => config('app.version'),
        'currentYear' => date('Y'),
    ]);
});
```

## Asset Versioning

Inertia uses asset versioning to ensure clients always have the latest assets. When assets change, Inertia triggers a full page reload.

By default, the adapter uses the Vite manifest file hash:

```php
// Automatic (default behavior)
// Uses: public/build/.vite/manifest.json
```

### Custom Version

```php
$inertia = Inertia::instance();

// Using manifest hash
$inertia->setVersion(md5_file(public_path('build/manifest.json')));

// Using deployment timestamp
$inertia->setVersion(config('app.deploy_version'));

// Using git commit hash
$inertia->setVersion(exec('git rev-parse --short HEAD'));
```

## API Reference

| Method | Description |
|--------|-------------|
| `Inertia::instance()` | Get the Inertia Adapter Instance |
| `Inertia::render($component, $props)` | Render an Inertia response |
| `Inertia::redirect($url, $status)` | Create a redirect response |
| `Inertia::back($status)` | Redirect to previous page |
| `Inertia::location($url)` | External redirect (409 response) |
| `Inertia::setRootView($view)` | Set the root Blade template |
| `Inertia::setVersion($version)` | Set the asset version |
| `Inertia::withEncryptedHistory($encrypt)` | Enable history encryption |
| `Inertia::withClearedHistory($clear)` | Clear encrypted history |
| `Inertia::share($key, $value)` | Share data globally |
| `Inertia::getShared($key, $default)` | Retrieve shared data |
| `Inertia::shareOnce($key, $callback)` | Share a once prop globally |
| `Inertia::flushShared()` | Clear all shared data |
| `Inertia::forceRefresh()` | Force the client to reload the page |
| `Inertia::composer($components, $callback)` | Register a view composer |
| `Inertia::lazy($callback)` | Create a lazy prop |
| `Inertia::optional($callback)` | Create an optional prop |
| `Inertia::always($callback)` | Create an always prop |
| `Inertia::defer($callback, $group)` | Create a deferred prop |
| `Inertia::merge($callback)` | Create a merge prop (append) |
| `Inertia::prepend($callback)` | Create a merge prop (prepend) |
| `Inertia::deepMerge($callback)` | Create a deep merge prop |
| `Inertia::once($callback)` | Create a once prop |

### Helper Function

| Function | Description |
|----------|-------------|
| `inertia()` | Get the Inertia instance |
| `inertia($component, $props)` | Render an Inertia response |

### Blade Directive

| Directive | Description |
|-----------|-------------|
| `@inertia` | Render the root element with page data |

### Router Macro

| Method | Description |
|--------|-------------|
| `Route::inertia($path, $component, $props)` | Register an Inertia route |

## License

MIT