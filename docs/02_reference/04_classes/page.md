---
title: Classes
---
All classes live in the `Blueprint\` namespace in `src/`.

## [`App`](../../../src/App.php)

Singleton: `App::create(Config $config)`, `App::instance()`.

`config`, `isDebug()`, `projectDir()`, `contentDir()`, `siteDir()`, `publicDir()`, `site()`, `redirects()`, `run()`, `handle($request)`, `request($uri, $method = "GET")` (for tests), `renderPage($page)`, `renderTemplate($name, $vars)`, `renderPartial($name, $vars)`.

## [`Config`](../../../src/Config.php)

`Config::load($configDir)`, `get("key.nested", $default)`, `all()`, `path("contentFolder")` (absolute path).

## [`Page`](../../../src/Page.php)

| | |
|---|---|
| `root`, `slug`, `num`, `isDraft`, `parent` | readonly props |
| `id()` | slug path, e.g. `blog/newest_entry` |
| `url()` | e.g. `/blog/newest_entry`, `/blog/` |
| `data()` | raw data as [`Data`](../../../src/Data.php) |
| `title()` | the `title` field, falls back to the slug |
| `type()`, `uid()` | page type (default `page`), unique id |
| `files()`, `file($filename)` | the page's [`File`](../../../src/File.php)s |
| `field($name)`, `$page->{field}()` | [field values](../02_fields/02_field-methods/page.md) |
| `blueprint()` | the page's [`Blueprint`](../../../src/Blueprint.php) |
| `children()`, `drafts()`, `allChildren()`, `child($slug)`, `hasChildren()` | collections ([illuminate/support](https://laravel.com/docs/collections)) |
| `isHome()`, `isListed()`, `isFolder()`, `status()`, `isEditable()` | |
| `update($values)`, `changeStatus($status)`, `changeNum($position)`, `changeSlug($slug)`, `changeType($type)`, `delete()`, `Page::create($parent, $slug, $data)` | write to the content folder (and add a uid) |
| `Page::current()`, `Page::find($path)` | |

## [`Site`](../../../src/Site.php)

`data()`, `update($values)`, `blueprint()`, `field($name)`, field methods, `children()`, `allChildren()`, `index()` (all pages), `homePage()`, `find($path, $includeDrafts = null)`, `findByUid($uid)`, `findFileByUid($uid)`.

## [`User`](../../../src/User.php)

`User::all()`, `User::find($emailOrUid)`, `User::current()`, `email()`, `uid()`, `role()`, `blueprint()`, `data()`, `field($name)`, field methods, `User::create()`, `verifyPassword()`, `changePassword()`, `update()`, `delete()`, `toArray()` (without password).

## [`Data`](../../../src/Data.php)

Read-only data: `$data->title`, `$data["title"]`, `$data->get("some.nested")`, `has()`, `merge()`, `toArray()`. Maps become `Data`, lists stay arrays, missing keys return `null`.

## [`Blueprint`](../../../src/Blueprint.php)

`Blueprint::load(...$names)`, `Blueprint::forPage($page)`, `Blueprint::find($name)`, `Blueprint::names($folder)`, `name`, `type()`, `title()`, `props()`, `get($key)`, `fields()`, `field($name)`, `tabs()`, `tab($name = null)`, `defaults()`, `validate($data)`, `toArray()`.

## [`BlueprintNormalizer`](../../../src/BlueprintNormalizer.php)

Converts raw blueprint props into tabs → columns → fields, see [Layout](../01_blueprints/01_layout/page.md).

## [`Field`](../../../src/Field.php)

A field definition of a blueprint: `name`, `type`, `props`, `label()`, `get($key)`, `hasValue()`, `isRequired()`, `fields()`, `defaultValue()`, `staticOptions()`, `validate($value)`, `toArray()`.

## [`File`](../../../src/File.php)

A file in a page folder: `filename()`, `name()`, `extension()`, `type()` (image, video, audio, document, archive, other), `mime()`, `size()`, `dimensions()`, `url()`, `id()`, `uid()`, `template()`, `data()`, `blueprint()`, field methods, `update($values)`, `changeName($name)`, `delete()`, `File::create($page, $filename, $contents, $data)`.

## [`Block`](../../../src/Block.php), [`Layout`](../../../src/Layout.php), [`LayoutColumn`](../../../src/LayoutColumn.php)

Blocks have `id()`, `type()`, `isHidden()`, `render()` and field methods for their content. Layouts have `columns()`, columns have `width()`, `span()` and `blocks()`.

## [`Uid`](../../../src/Uid.php)

`Uid::generate()`, `Uid::parse("page://abc")`.

## [`FieldValue`](../../../src/FieldValue.php)

The value of a field, see [Field methods](../02_fields/02_field-methods/page.md).

## [`Values`](../../../src/Values.php)

Structure rows and object fields with field methods. Field methods come from the [`HasFields`](../../../src/HasFields.php) trait.

## [`Panel`](../../../src/Panel.php)

The panel app and its [REST API](../07_panel-api/page.md), [`Session`](../../../src/Session.php) (login, CSRF token), [`ApiError`](../../../src/ApiError.php).

## Others

[`ContentFile`](../../../src/ContentFile.php) (reads json, yaml and markdown files), [`Redirects`](../../../src/Redirects.php), [`Redirect`](../../../src/Redirect.php) (exception thrown by `redirect()`).
