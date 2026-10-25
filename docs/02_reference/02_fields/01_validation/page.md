---
title: Validation
---
`$blueprint->validate($data)` checks data against the fields of a blueprint, including the nested fields of `structure` and `object` fields. It returns the error messages per field path, an empty array if everything is valid.

```php
$errors = $page->blueprint()->validate($page->data());
// [
//   "title" => ["Title is required"],
//   "links.1.url" => ["Url is not a valid url value"],
// ]
```

Empty values (`null`, `""`, `[]`) are only checked for `required`. Display fields are never validated.

| Rule | Applies to |
|---|---|
| `required: true` | all fields with a value |
| `min`, `max` | `number`, `range`: the value; list fields (`tags`, `checkboxes`, `multiselect`, pickers, `structure`, `blocks`, …): the number of entries |
| `minlength`, `maxlength` | text values |
| `pattern` | text values, regex without delimiters, e.g. `pattern: "[a-z]+"` |
| type | `email`, `url`, `date`, `time` must be valid; `number`/`range` must be numeric |
| options | `select`, `radio`, `toggles`: the value must be one of the static `options` |

Source: [`Field::validate()`](../../../../src/Field.php), [`Blueprint::validate()`](../../../../src/Blueprint.php).
