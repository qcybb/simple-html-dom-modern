# PHP Simple HTML DOM Parser (PHP 8.4+)

![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)
![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)
![Version 2.0.0](https://img.shields.io/badge/version-2.0.0-blue.svg)

A faster, leaner, bug-fixed rewrite of the classic
[PHP Simple HTML DOM Parser](https://sourceforge.net/projects/simplehtmldom/) 1.9.1
for modern PHP. It is a **drop-in replacement**: same file name, same functions,
same classes, same methods. Swap the file and your existing code keeps working.

Parse HTML, find elements with CSS-style selectors, read and change attributes
and text, and write the result back out. It also copes with the messy,
half-broken HTML you find on real pages.

## Why this version?

The original has not kept up with PHP, and it has some serious problems:

- **Slow on big pages.** A 5 MB page took 95 seconds to load. It now takes about 2 seconds.
- **Hangs and crashes.** A page containing the text `___noise___` made it loop forever, and selectors like `li + li` crashed PHP.
- **Wrong results.** Selectors such as `div p` and `tr td` returned wrong matches on unclosed tags (`<li>`, `<p>`, `<td>`). Editing attributes could corrupt the markup.
- **Memory hungry.** It copied the document over and over, and `clear()` did not release the nodes.

| Test on a 5 MB page (~500k nodes) | 1.9.1 | 2.0.0 |
|---|---|---|
| Load | 95.7 s | 2.2 s |
| `find('div.item a')` | 3.3 s | 0.9 s |
| `find('li')` | 2.8 s | 0.4 s |
| Retained memory | 395 MB | 345 MB |
| Peak memory | 417 MB | 364 MB |

See [CHANGELOG.md](CHANGELOG.md) for the full list of fixes and the few
intentional behavior changes.

## Requirements

- PHP 8.4 or newer. Tested on PHP 8.3 and 8.4.
- The `pcre` extension, which is always present.
- Optional: `mbstring` (charset detection) and `iconv` (charset conversion).

## Installation

Copy `simple_html_dom.php` into your project and include it:

```php
require_once 'simple_html_dom.php';
```

No Composer, no dependencies. If you are replacing an existing copy of the
library, just overwrite the old file.

## Usage

### Load HTML

```php
// From a string
$html = str_get_html('<div id="main"><p class="intro">Hello <b>world</b></p></div>');

// From a URL or file
$html = file_get_html('https://example.com/');
```

Both return `false` if the input is empty or larger than `MAX_FILE_SIZE` (20 MB by default).

### Find elements

```php
// All links
foreach ($html->find('a') as $a) {
    echo $a->href, ' - ', $a->plaintext, "\n";
}

// First match / last match
$first = $html->find('p.intro', 0);
$last  = $html->find('li', -1);

// Combine selectors
$items = $html->find('div#main > p.intro, ul.menu li a[href^=http]');
```

### Read and change content

```php
$p = $html->find('p.intro', 0);

echo $p->plaintext;   // Hello world
echo $p->innertext;   // Hello <b>world</b>
echo $p->outertext;   // <p class="intro">Hello <b>world</b></p>

$p->class = 'intro highlight';
$p->setAttribute('data-id', '42');
$p->removeAttribute('data-id');
$p->addClass('big');
$p->innertext = 'New content';

echo $html;           // or $html->save()
```

### Navigate the tree

```php
$li = $html->find('li', 0);

$li->parent();
$li->next_sibling();
$li->prev_sibling();
$li->first_child();
$li->children();
$li->find_ancestor_tag('ul');
```

### Free memory

```php
$html->clear();   // always call this when you are done with a document
unset($html);
```

This matters when you parse many pages in a loop or work with large documents.

## Supported selectors

| Selector | Meaning |
|---|---|
| `tag`, `*` | Tag name, any tag |
| `#id`, `.class`, `.a.b` | ID and class (multiple classes allowed) |
| `[attr]`, `[!attr]` | Attribute present / not present |
| `[attr=val]`, `[attr!=val]` | Equals / not equals |
| `[attr^=val]`, `[attr$=val]`, `[attr*=val]` | Starts with / ends with / contains |
| `[attr~=val]`, `[attr\|=val]` | Word match / prefix match |
| `[attr=val i]` | Case-insensitive match |
| `a b`, `a > b`, `a + b`, `a ~ b` | Descendant, child, next sibling, later siblings |
| `a, b` | Selector list |
| `li[2]` | The 2nd `li` inside its parent |
| `text`, `comment` | Text and comment nodes |

## Compatibility notes

This is intended to be a straight swap for 1.9.1, with these deliberate differences:

- `$dom->getElementsByTagName($name)` returns an array (it used to return a single node).
- `a ~ b` no longer matches `a` itself.
- Removed attributes are really removed (no `null` left in `getAllAttributes()`).
- `load_file()` and `loadFile()` return the dom on success.
- The per-node destructor is gone. Use `clear()`.

The full list is in [CHANGELOG.md](CHANGELOG.md).

## Testing

This version was checked against the original 1.9.1 on a set of fuzzed, malformed
HTML documents plus a sweep of the public API. Where results differ, it is
because the original was wrong. If you find a page where the output differs
from 1.9.1, please open an issue with the HTML and the selector.

## Credits and license

Based on PHP Simple HTML DOM Parser 1.9.1 (Rev. 291).

- Original authors: S.C. Chen, John Schlick, Rus Carroll, logmanoriginal
- Contributors: Yousuke Kumakura, Vadim Voituk, Antcs
- Acknowledged: Jose Solorzano ([php-html](https://sourceforge.net/projects/php-html/))
- Original project: <https://sourceforge.net/projects/simplehtmldom/>

Released under the [MIT License](LICENSE). This is an independent rewrite and is
not the official release.
