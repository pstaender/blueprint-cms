---
title: Models
sort: 10
---
Models store records that aren't pages: ratings, form submissions, orders, … A model is a php class in `site/models/` (or a plugin's `models/`), named like its file, that extends `Blueprint\Model`:

```php
<?php // site/models/PageRating.php

class PageRating extends Blueprint\Model
{
}
```

The model name is the class name in camelCase (`pageRating`). Its records are stored as a list of json objects in a collection file in the content folder, by default the plural of the model name: `content/pageRatings.json`. Every record gets a `uid`, `createdAt` and `modifiedAt`. Another file name:

```php
class PageRating extends Blueprint\Model
{
    public const COLLECTION = "ratings"; // content/ratings.json
}
```

Model files are loaded when the app is created. A class can be in any namespace; a site model wins over a plugin's model with the same file name.

## Records

```php
$rating = PageRating::create(["page" => "guides/content", "ratingValue" => 1]);
$rating->uid();                       // "19qiajkmcaw3x8bp"
$rating->ref();                       // "pageRating://19qiajkmcaw3x8bp"
$rating->ratingValue();               // field methods, like pages
$rating->data()->ratingValue;         // raw data

PageRating::all();                    // a Collection of all records
PageRating::find("19qiajkmcaw3x8bp"); // by uid or reference, also from the trash
PageRating::where("page", "guides/content");
record("pageRating://19qiajkmcaw3x8bp"); // any model's record by reference

$rating = $rating->update(["ratingValue" => -1]); // null removes a field
$rating->delete();
```

`create()` fills the `default` values of the blueprint, it doesn't validate (the panel does). Writes lock the collection file, so concurrent requests (e.g. two visitors rating at the same time) don't overwrite each other's records. The whole file is read for every query, so collections work best with up to some thousand records.

## Blueprint

`site/blueprints/collections/{name}.yml` defines the fields like a page blueprint (tabs, columns, all field types) and a few options:

```yaml
# site/blueprints/collections/pageRating.yml
title: Page ratings
icon: star
panel: true          # listed under "Collections" in the panel
softDelete: true     # deleted records go to the trash
files: false         # no files for the records in the panel
perPage: 25          # records per page in the panel (default 10)
searchableFields:    # searched in the panel (default: all fields)
  - page
  - ip
fields:
  page:
    type: text
    required: true
  ratingValue:
    type: number
    min: -1
    max: 1
  ip:
    type: text
    label: IP
```

## Panel

Models with `panel: true` are listed under *Collections* in the sidebar, for every logged in user. A collection shows its records next to the open record: search (in `searchableFields`), sort by created or modified date (newest or oldest first), page through them (`perPage`, arrows below the list), step to the previous or next record with the arrows in the header of the open record (or ← / →, also across pages), add, edit, delete and upload files (unless `files: false`). The title of a record in the list is its `title` or `name` field, the first searchable field with a value or the uid; models can override `panelTitle()`:

```php
public function panelTitle(): string
{
    return ($this->data->ratingValue === 1 ? "👍 " : "👎 ") . $this->data->page;
}
```

## Trash

With `softDelete: true` deleting moves a record to `content/.trash/{collection}/{uid}.json` and its files to `content/.trash/{collection}/files/`. `PageRating::trashed()` lists them, `restore()` moves a record and its files back, deleting a trashed record deletes it permanently, `PageRating::emptyTrash()` deletes all of them. In the panel the trash is an expandable item below the records, with buttons to restore or delete each record permanently and to empty the whole trash.

## Files

```php
$rating->addFile("Screenshot.PNG", $contents); // "screenshot.png", a string or a stream
$rating->files();                              // ["screenshot.png" => "/…/content/.database_files/pageRatings/{uid}_screenshot.png"]
$rating->file("screenshot.png");               // the path or null
$rating->deleteFile("screenshot.png");
```

Files of records are stored in `content/.database_files/{collection}/{uid}_{filename}` with a sanitized, lowercase filename. They aren't served by the website (only the files of pages are); the panel downloads them through its API. Deleting a record permanently deletes its files.

`.trash` and `.database_files` are reserved: they are never pages, although a folder with a leading `.` is a draft otherwise.

## Example: rating pages

These docs ask "Was this page helpful?" below every page, with the `thumbs-up` and `thumbs-down` icons of the panel. `site/models/PageRating.php` stores the rating, once per page and visitor:

```php
public static function rate(Page $page, int $value, string $ip): ?self
{
    $reference = self::pageReference($page);

    if (self::all()->contains(fn(self $rating) => $rating->data->page === $reference && $rating->data->ip === $ip)) {
        return null;
    }

    return self::create(["page" => $reference, "ratingValue" => $value, "ip" => $ip]);
}
```

The form in the layout posts to a php page (`docs/rating/page.php`, hidden in the menu with `hidden: true` in its `page.json`), which calls `PageRating::rate()` and answers with json. Only php pages accept POST requests, see [Templates](/guides/templates#page-php-files).
