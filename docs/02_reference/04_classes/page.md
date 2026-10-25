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
| `template()` | |
| `field($name)`, `$page->{field}()` | [field values](../02_fields/02_field-methods/page.md) |
| `blueprint()` | the page's [`Blueprint`](../../../src/Blueprint.php) |
| `children()`, `drafts()`, `allChildren()`, `child($slug)`, `hasChildren()` | collections ([illuminate/support](https://laravel.com/docs/collections)) |
| `isHome()`, `isListed()`, `isFolder()` | |
| `Page::current()`, `Page::find($path)` | |

## [`Site`](../../../src/Site.php)

`data()`, `blueprint()`, `field($name)`, field methods, `children()`, `allChildren()`, `homePage()`, `find($path, $includeDrafts = null)`.

## [`User`](../../../src/User.php)

`User::all()`, `User::find($emailOrUid)`, `User::current()`, `email()`, `uid()`, `role()`, `blueprint()`, `data()`, `field($name)`, field methods.

## [`Data`](../../../src/Data.php)

Read-only data: `$data->title`, `$data["title"]`, `$data->get("some.nested")`, `has()`, `merge()`, `toArray()`. Maps become `Data`, lists stay arrays, missing keys return `null`.

## [`Blueprint`](../../../src/Blueprint.php)

`Blueprint::load(...$names)`, `Blueprint::forPage($page)`, `Blueprint::find($name)`, `Blueprint::names($folder)`, `name`, `type()`, `title()`, `props()`, `get($key)`, `fields()`, `field($name)`, `tabs()`, `tab($name = null)`, `defaults()`, `validate($data)`, `toArray()`.

## [`BlueprintNormalizer`](../../../src/BlueprintNormalizer.php)

Converts raw blueprint props into tabs → columns → fields, see [Layout](../01_blueprints/01_layout/page.md).

## [`Field`](../../../src/Field.php)

A field definition of a blueprint: `name`, `type`, `props`, `label()`, `get($key)`, `hasValue()`, `isRequired()`, `fields()`, `defaultValue()`, `staticOptions()`, `validate($value)`, `toArray()`.

## [`FieldValue`](../../../src/FieldValue.php)

The value of a field, see [Field methods](../02_fields/02_field-methods/page.md).

## [`Values`](../../../src/Values.php)

Structure rows and object fields with field methods. Field methods come from the [`HasFields`](../../../src/HasFields.php) trait.

## Others

[`ContentFile`](../../../src/ContentFile.php) (reads json, yaml and markdown files), [`Redirects`](../../../src/Redirects.php), [`Redirect`](../../../src/Redirect.php) (exception thrown by `redirect()`).
