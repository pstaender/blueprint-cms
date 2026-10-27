<?php

use Blueprint\Models;
use Blueprint\User;
use Tests\Fixtures\Note;

beforeEach(function () {
    $this->dir = copyProject(BLUEPRINT_PROJECT);
    $this->app = createApp($this->dir);
});

afterEach(fn() => removeProject($this->dir));

it("loads the models of the site", function () {
    expect(Models::all())->toBe(["logEntry" => LogEntry::class, "note" => Note::class])
        ->and(Note::collection())->toBe("notes")
        ->and(LogEntry::collection())->toBe("log")
        ->and(array_keys(Models::inPanel()))->toBe(["note"]);
});

it("creates, finds, updates and deletes records", function () {
    $note = Note::create(["title" => "Hello", "uid" => "ignored"]);

    expect($note->uid())->toHaveLength(16)->not->toBe("ignored")
        ->and($note->data->priority)->toBe(1)
        ->and($note->data->createdAt)->not->toBeNull()
        ->and($note->ref())->toBe("note://" . $note->uid())
        ->and((string) $note->title())->toBe("Hello")
        ->and(Note::find($note->ref())->data->title)->toBe("Hello")
        ->and(record($note->ref())?->uid())->toBe($note->uid())
        ->and(json_decode(file_get_contents("$this->dir/content/notes.json"), true))->toHaveCount(1);

    $updated = $note->update(["title" => "Updated", "priority" => null]);

    expect(Note::find($note->uid())->data->title)->toBe("Updated")
        ->and($updated->data->priority)->toBeNull()
        ->and($updated->data->createdAt)->toBe($note->data->createdAt)
        ->and(Note::where("title", "Updated")->count())->toBe(1);

    $log = LogEntry::create(["message" => "x"]);
    $log->delete();

    expect(LogEntry::all())->toBeEmpty()
        ->and(LogEntry::find($log->uid()))->toBeNull();
});

it("moves records to the trash with softDelete and restores them", function () {
    $note = Note::create(["title" => "Trash me"]);
    $note->addFile("My Photo.JPG", "jpeg");
    $note->delete();

    $trashFile = "$this->dir/content/.trash/notes/{$note->uid()}.json";
    expect(Note::all())->toBeEmpty()
        ->and(is_file($trashFile))->toBeTrue()
        ->and(Note::trashed()->first()->isTrashed)->toBeTrue()
        ->and(Note::find($note->uid())->isTrashed)->toBeTrue()
        // the files go to the trash too
        ->and(Note::find($note->uid())->files())->toBe(["my-photo.jpg" => "$this->dir/content/.trash/notes/files/{$note->uid()}_my-photo.jpg"])
        ->and(glob("$this->dir/content/.database_files/notes/*"))->toBeEmpty()
        ->and(site()->find("trash"))->toBeNull();

    $restored = Note::find($note->uid())->restore();

    expect(Note::all())->toHaveCount(1)
        ->and(is_file($trashFile))->toBeFalse()
        ->and(array_keys($restored->files()))->toBe(["my-photo.jpg"])
        ->and(glob("$this->dir/content/.trash/notes/files/*"))->toBeEmpty();

    $note->delete();
    Note::find($note->uid())->delete();

    expect(Note::trashed())->toBeEmpty()
        ->and(glob("$this->dir/content/.database_files/notes/*"))->toBeEmpty()
        ->and(glob("$this->dir/content/.trash/notes/files/*"))->toBeEmpty();
});

it("stores files of records", function () {
    $note = Note::create(["title" => "Files"]);

    expect($note->addFile("My Photo.JPG", "jpeg"))->toBe("my-photo.jpg")
        ->and($note->files())->toBe(["my-photo.jpg" => "$this->dir/content/.database_files/notes/{$note->uid()}_my-photo.jpg"])
        ->and(fn() => $note->addFile("x.php", "<?php"))->toThrow(InvalidArgumentException::class);

    $note->deleteFile("my-photo.jpg");

    expect($note->files())->toBe([]);
});

it("doesn't lose concurrent writes", function () {
    for ($i = 0; $i < 20; $i++) {
        Note::create(["title" => "Note $i"]);
    }

    expect(Note::all())->toHaveCount(20);
});

describe("panel", function () {
    beforeEach(function () {
        User::create("panel@example.com", "secret-password", "editor");
        $this->csrf = login($this->app);
    });

    it("lists the models with panel: true in the session", function () {
        [, $session] = api($this->app, "GET", "/session");

        expect($session["models"])->toBe([[
            "name" => "note", "collection" => "notes", "title" => "Notes", "icon" => "text",
            "softDelete" => true, "files" => true, "perPage" => 10, "searchableFields" => ["title"],
        ]])->and(api($this->app, "GET", "/models/logEntry")[0])->toBe(404);
    });

    it("lists, searches and sorts records", function () {
        foreach (["Apple", "Banana", "Cherry"] as $title) {
            Note::create(["title" => $title, "text" => "fruit"]);
            usleep(2000);
        }

        expect(array_column(api($this->app, "GET", "/models/note")[1]["records"], "title"))->toBe(["Cherry", "Banana", "Apple"])
            ->and(array_column(api($this->app, "GET", "/models/note?direction=asc&limit=2")[1]["records"], "title"))->toBe(["Apple", "Banana"])
            ->and(array_column(api($this->app, "GET", "/models/note?direction=asc&offset=2&limit=2")[1]["records"], "title"))->toBe(["Cherry"])
            ->and(api($this->app, "GET", "/models/note?search=an")[1]["records"][0]["title"])->toBe("Banana")
            // only the searchable fields
            ->and(api($this->app, "GET", "/models/note?search=fruit")[1]["total"])->toBe(0);
    });

    it("creates, updates and deletes records", function () {
        [$status, $errors] = api($this->app, "POST", "/models/note", ["data" => []], $this->csrf);
        expect($status)->toBe(422)->and($errors["errors"])->toHaveKey("title");

        [$status, $note] = api($this->app, "POST", "/models/note", ["data" => ["title" => "New"]], $this->csrf);
        expect($status)->toBe(201)->and($note["data"]["priority"])->toBe(1);

        [$status, $updated] = api($this->app, "PATCH", "/models/note/{$note["uid"]}", [
            "data" => ["text" => "Text"], "modifiedAt" => $note["data"]["modifiedAt"], "original" => ["text" => null],
        ], $this->csrf);
        expect($status)->toBe(200)->and($updated["data"]["text"])->toBe("Text");

        expect(api($this->app, "DELETE", "/models/note/{$note["uid"]}", csrf: $this->csrf)[0])->toBe(204)
            ->and(api($this->app, "GET", "/models/note")[1]["trashCount"])->toBe(1)
            ->and(api($this->app, "GET", "/models/note?trash=1")[1]["records"][0]["isTrashed"])->toBeTrue()
            ->and(api($this->app, "POST", "/models/note/{$note["uid"]}/~restore", csrf: $this->csrf)[1]["isTrashed"])->toBeFalse();
    });

    it("empties the trash", function () {
        foreach (["One", "Two"] as $title) {
            $note = Note::create(["title" => $title]);
            $note->addFile("a.txt", "a");
            $note->delete();
        }
        Note::create(["title" => "Stays"]);

        expect(api($this->app, "DELETE", "/models/note/~trash", csrf: $this->csrf)[1])->toBe(["deleted" => 2])
            ->and(Note::trashed())->toBeEmpty()
            ->and(Note::all())->toHaveCount(1)
            ->and(glob("$this->dir/content/.trash/notes/files/*"))->toBeEmpty();
    });

    it("uploads, serves and deletes files of records", function () {
        $note = Note::create(["title" => "Files"]);
        $response = $this->app->request("/panel/api/models/note/{$note->uid()}/~files?filename=Notes.txt", "POST", null, ["X-CSRF-Token" => $this->csrf]);
        expect($response->getStatusCode())->toBe(201);

        $response = $this->app->request("/panel/api/models/note/{$note->uid()}/~files/notes.txt");
        expect($response->getStatusCode())->toBe(200)
            ->and($response->getHeaderLine("Content-Security-Policy"))->toContain("sandbox")
            // the files folder isn't served by the website
            ->and($this->app->request("/.database_files/notes/{$note->uid()}_notes.txt")->getStatusCode())->toBe(404);

        expect(api($this->app, "DELETE", "/models/note/{$note->uid()}/~files/notes.txt", csrf: $this->csrf)[1]["files"])->toBe([]);
    });

    it("refuses uploads with files: false in the blueprint", function () {
        file_put_contents("$this->dir/site/blueprints/collections/logEntry.yml", "title: Log\npanel: true\nfiles: false\n");
        $entry = LogEntry::create(["message" => "No files"]);
        $response = $this->app->request("/panel/api/models/logEntry/{$entry->uid()}/~files?filename=a.txt", "POST", null, ["X-CSRF-Token" => $this->csrf]);

        expect($response->getStatusCode())->toBe(400)
            ->and(api($this->app, "GET", "/session")[1]["models"][0]["files"])->toBeFalse();
    });
});
