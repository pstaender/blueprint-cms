---
title: Classes
sort: 4
---
All classes live in the `Blueprint\` namespace in `src/`.

## `App`

Singleton: `App::create(Config $config)`, `App::instance()`.

`config`, `isDebug()`, `projectDir()`, `contentDir()`, `siteDir()`, `publicDir()`, `site()`, `redirects()`, `run()`, `handle($request)`, `request($uri, $method = "GET")` (for tests), `renderPage($page)`, `renderTemplate($name, $vars)`, `renderPartial($name, $vars)`.

## `Config`

`Config::load($configDir)`, `get("key.nested", $default)`, `all()`, `path("contentFolder")` (absolute path).

## `Page`

| | |
|---|---|
| `root`, `slug`, `num`, `isDraft`, `parent` | readonly props |
| `id()` | slug path, e.g. `blog/newest_entry` |
| `url()` | e.g. `/blog/newest_entry`, `/blog/` |
| `data()` | raw data as `Data` |
| `title()` | the `title` field, falls back to the slug |
| `type()`, `uid()` | page type (default `page`), unique id |
| `files()`, `file($filename)`, `sortFiles($filenames)` | the page's `File`s |
| `field($name)`, `$page->{field}()` | [field values](/reference/fields/field-methods) |
| `blueprint()` | the page's `Blueprint` |
| `children()`, `drafts()`, `allChildren()`, `child($slug)`, `hasChildren()` | collections ([illuminate/support](https://laravel.com/docs/collections)) |
| `isHome()`, `isListed()`, `isFolder()`, `status()`, `isEditable()` | |
| `update($values)`, `changeStatus($status)`, `changeNum($position)`, `changeSlug($slug)`, `changeType($type)`, `changeContentFormat($format)`, `delete()`, `Page::create($parent, $slug, $data)` | write to the content folder (and fill `uid`, `createdAt`, `modifiedAt`) |
| `contentFormat()` | `json`, `md` or `json+md` |
| `Page::current()`, `Page::find($path)` | |

## `Site`

`data()`, `update($values)`, `blueprint()`, `field($name)`, field methods, `children()`, `allChildren()`, `index()` (all pages), `homePage()`, `find($path, $includeDrafts = null)`, `findByUid($uid)`, `findFileByUid($uid)`.

## `User`

`User::all()`, `User::find($emailOrUid)`, `User::current()`, `email()`, `uid()`, `role()`, `isAdmin()`, `blueprint()`, `data()`, `field($name)`, field methods, `User::create()`, `User::roles()`, `verifyPassword()`, `changePassword()`, `update()`, `delete()`, `toArray()` (without password).

## `Data`

Read-only data: `$data->title`, `$data["title"]`, `$data->get("some.nested")`, `has()`, `merge()`, `toArray()`. Maps become `Data`, lists stay arrays, missing keys return `null`.

## `Blueprint`

`Blueprint::load(...$names)`, `Blueprint::forPage($page)`, `Blueprint::forType($type)`, `Blueprint::find($name)`, `Blueprint::names($folder)`, `name`, `type()`, `title()`, `props()`, `get($key)`, `fields()`, `field($name)`, `tabs()`, `tab($name = null)`, `defaults()`, `validate($data)`, `toArray()`.

## `BlueprintNormalizer`

Converts raw blueprint props into tabs → columns → fields, see [Layout](/reference/blueprints/layout).

## `Field`

A field definition of a blueprint: `name`, `type`, `props`, `label()`, `get($key)`, `hasValue()`, `isRequired()`, `fields()`, `defaultValue()`, `staticOptions()`, `validate($value)`, `toArray()`.

## `File`

A file in a page folder: `filename()`, `name()`, `extension()`, `type()` (image, video, audio, document, archive, other), `mime()`, `size()`, `modified()`, `dimensions()`, `url()`, `id()`, `uid()`, `template()`, `data()`, `blueprint()`, field methods, `isUsed()` (only used files are public), `update($values)`, `changeName($name)`, `replace($contents)` (same filename, uid and data; restored if not accepted), `delete()`, `File::create($page, $filename, $contents, $data)`, `File::resolveUrls($html, $page)`.

## `Block`, `Layout`, `LayoutColumn`

Blocks have `id()`, `type()`, `isHidden()`, `render()` and field methods for their content. Layouts have `columns()`, columns have `width()`, `span()` and `blocks()`.

## `Uid`

`Uid::generate()`, `Uid::parse("page://abc")`.

## `FieldValue`

The value of a field, see [Field methods](/reference/fields/field-methods).

## `Values`

Structure rows and object fields with field methods. Field methods come from the `HasFields` trait.

## `Panel`

The panel app and its [REST API](/reference/panel-api), `Session` (login, CSRF token), `ApiError`.

## `ImageOptimizer`

Converts content images to AVIF (`url($file, $maxFileSize, $quality)`, `replaceImages($html, $optimize)`, `isSupported()`), with `GdImageFile` or `ImagickImage` (`Image`), see [Images](/guides/images). `App::imageOptimizer()`, `App::resolveFile($reference, $page)`.

## Others

`ContentFile` (reads json, yaml and markdown files), `Redirects`, `Redirect` (exception thrown by `redirect()`).
