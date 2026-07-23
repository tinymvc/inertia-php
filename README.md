# Inertia.js v3 Adapter for TinyMVC

A server-side [Inertia.js v3](https://inertiajs.com/docs/v3) adapter for
[TinyMVC](https://github.com/tinymvc/tinycore). It implements client-side
rendering only; SSR is intentionally not included.

The adapter requires PHP 8.2 or newer and TinyCore 3.x.

## Installation

```bash
composer require tinymvc/inertia-php
```

Register the provider:

```php
// bootstrap/providers.php
return [
    \Inertia\InertiaServiceProvider::class,
];
```

The provider registers the Inertia singleton, the `@inertia` Blade directive,
and the `Route::inertia()` macro.

## Root template

```blade
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['app.tsx', 'app.css'])
</head>
<body>
    @inertia
</body>
</html>
```

In v3, the initial page object is stored in a JSON script element:

```html
<script data-page="app" type="application/json">{"component":"Home", ...}</script>
<div id="app"></div>
```

Use `@inertia('portal')` for a custom mount id. The root Blade view and DOM
mount id are configured independently:

```php
Inertia::setRootView('layouts.admin');
Inertia::setRootElementId('portal');
```

Your client-side `createInertiaApp()` configuration must use the same mount id.

## Responses

```php
use Inertia\Facades\Inertia;

return Inertia::render('Users/Index', [
    'users' => User::all(),
]);

// Equivalent helper:
return inertia('Users/Index', ['users' => User::all()]);
```

Simple routes may use the router macro:

```php
Route::inertia('/about', 'About');
```

The adapter automatically adds an empty `errors` prop, performs partial prop
resolution, checks asset versions, emits v3 page metadata, and returns the
required `X-Inertia` and `Vary` response headers.

## Shared data

```php
Inertia::share('appName', config('app.name'));

Inertia::share([
    'auth' => fn () => [
        'user' => request()->user(),
    ],
    'locale' => 'en',
]);
```

Shared keys are exposed in the v3 page object's `sharedProps` metadata for
instant visits. Component props take precedence over shared props.

Authentication data is not shared implicitly. Define it explicitly so each
application controls its own shape and authorization boundary.

## Prop types

Regular closures are evaluated only when their prop survives partial-reload
filtering:

```php
return inertia('Users/Index', [
    'users' => fn () => User::all(),
    'companies' => fn () => Company::all(),
]);
```

### Optional and always

```php
return inertia('Reports', [
    // Excluded until explicitly requested with `only`.
    'details' => Inertia::optional(fn () => Report::details()),

    // Included even when a partial reload did not request it.
    'notifications' => Inertia::always(fn () => Notification::count()),
]);
```

```js
router.reload({ only: ['details'] })
```

Inertia v3 removed `lazy()` and `LazyProp`; use `optional()` instead.

### Deferred

```php
return inertia('Dashboard', [
    'permissions' => Inertia::defer(fn () => Permission::all()),
    'teams' => Inertia::defer(fn () => Team::all(), 'attributes'),
    'projects' => Inertia::defer(fn () => Project::all(), 'attributes'),
]);
```

Deferred failures can be rescued and reported to the client's `<Deferred>`
rescue slot:

```php
'permissions' => Inertia::defer(
    fn () => Permission::all(),
    rescue: true,
),
```

### Merge

```php
return inertia('Feed', [
    // Append at the prop root.
    'tags' => Inertia::merge($tags),

    // Append only `data`, replacing the other pagination fields.
    'users' => Inertia::merge(fn () => User::paginate())
        ->append('data', matchOn: 'id'),

    // Prepend one nested collection and append another.
    'dashboard' => Inertia::merge($dashboard)
        ->prepend('announcements')
        ->append('activities'),

    // Deep merge the whole value and match nested messages by id.
    'chat' => Inertia::deepMerge($chat)->matchOn('messages.id'),
]);
```

Merge metadata is omitted for props named in the `X-Inertia-Reset` header, as
required by the v3 protocol.

### Once

```php
return inertia('Billing', [
    'plans' => Inertia::once(fn () => Plan::all()),
    'rates' => Inertia::once(fn () => Rate::all())->until(3600),
    'roles' => Inertia::once(fn () => Role::all())->as('shared-roles'),
    'features' => Inertia::once(fn () => Feature::all())->fresh($changed),
]);
```

Once behavior can also be combined with optional, deferred, and merge props:

```php
'report' => Inertia::optional(fn () => Report::make())->once(),
'stats' => Inertia::defer(fn () => Stats::make())->once(),
'activity' => Inertia::merge(fn () => Activity::recent())->once(),
```

Globally shared once props:

```php
Inertia::shareOnce('countries', fn () => Country::all())
    ->until(86400);
```

### Nested props

V3 prop types work inside nested arrays and closures, and partial reload
headers support dot notation:

```php
return inertia('Dashboard', [
    'auth' => [
        'user' => request()->user(),
        'notifications' => Inertia::defer(fn () => Notification::all()),
        'invoices' => Inertia::optional(fn () => Invoice::all()),
    ],
]);
```

```js
router.reload({ only: ['auth.notifications'] })
```

For reusable prop objects, implement `ProvidesInertiaProperties` to contribute
multiple props or `ProvidesInertiaProperty` to resolve one contextual value.
Both are resolved at any nesting depth and receive the current TinyMVC request.

### Infinite scroll

`scroll()` emits `scrollProps` and merge metadata and honors
`X-Inertia-Infinite-Scroll-Merge-Intent`:

```php
'posts' => Inertia::scroll($paginator),
```

TinyMVC's `Spark\Utils\Paginator` is detected automatically. For a custom
paginator, provide metadata:

```php
'posts' => Inertia::scroll(
    $result,
    wrapper: 'data',
    metadata: fn ($result) => [
        'pageName' => 'page',
        'previousPage' => $result['previous'],
        'nextPage' => $result['next'],
        'currentPage' => $result['current'],
    ],
),
```

## Flash data

V3 flash data lives at `page.flash` and is not persisted in browser history:

```php
Inertia::flash('message', 'User created.');
return inertia()->redirect('/users');

// Or:
return Inertia::flash([
    'message' => 'User created.',
    'userId' => $user->id,
])->back();
```

TinyMVC's conventional `info`, `success`, and `error` flash keys are also moved
to `page.flash` automatically.

## History

```php
return inertia()
    ->encryptHistory()
    ->render('Account/Settings', $props);

return inertia()
    ->clearHistory()
    ->render('Auth/Login');
```

`encryptHistory` and `clearHistory` are only included in the page object when
true, as required by Inertia v3.

## Redirects

```php
return inertia()->redirect('/users');
return inertia()->back();
return inertia()->location('https://example.com');
```

Redirects after `PUT`, `PATCH`, and `DELETE` become `303` responses. External
Inertia redirects use `409` plus `X-Inertia-Location`; fragment redirects use
the v3 `X-Inertia-Redirect` header.

To preserve the fragment from the original URL across a redirect:

```php
return inertia()->preserveFragment()->redirect('/article/new-slug');
```

## Asset versioning

The adapter hashes `public/build/.vite/manifest.json` by default:

```php
Inertia::setBuildDirectory('build');
```

You may provide a fixed or lazy version:

```php
Inertia::version(config('app.deploy_version'));
Inertia::version(fn () => config('app.deploy_version'));
```

On a mismatched Inertia `GET`, the adapter returns `409` with the current URL in
`X-Inertia-Location` before resolving page props.

## Testing

```bash
composer test
```

## License

MIT
