# Changelog

## 2.0.0 - 2026-11-26

Complete rewrite of PHP Simple HTML DOM Parser 1.9.1 (Rev. 291) for PHP 8.4+.
All public functions, classes, methods, properties and constants are kept, so
it is a drop-in replacement. File size is down from 59,287 to about 45,700 bytes.

Tested on PHP 8.4 with `E_ALL` (no warnings or deprecations), against the
original on 218 fuzzed documents plus a sweep of the public API.

### Performance and memory

Measured on a 5 MB page with about 500k nodes:

| | 1.9.1 | 2.0.0 |
|---|---|---|
| Load | 95.7 s | 2.2 s |
| `find('div.item a')` | 3.3 s | 0.9 s |
| `find('li')` | 2.8 s | 0.4 s |
| Retained memory | 395 MB | 345 MB |
| Peak memory | 417 MB | 364 MB |

- Noise removal (scripts, styles, comments, CDATA, `<?php ?>`, `<code>`) now runs
  in a single pass. Before, the whole document was copied once per matched block.
- Descendant searches walk the node list in place instead of copying it with
  `array_slice()` at every step.
- Text nodes are no longer looked up with `in_array()` / `array_search()` over
  large arrays.
- The source HTML is freed once parsing and charset detection are done.
- Identical per-attribute spacing and quote data is shared between nodes
  (copy-on-write), instead of one array per attribute.
- `clear()` now releases all nodes (the original left them allocated) and no
  longer leaves `nodes` or `children` as `null`.
- Charset guessing no longer converts the whole document with `iconv()` just to
  test it.
- `is_utf8()` uses `preg_match('//u')`, which is native and much faster.
- `str_starts_with()`, `str_ends_with()` and `str_contains()` replace regex
  matching in attribute selectors.

### Fixed

#### Hangs and crashes

- A page containing the literal text `___noise___` caused an endless loop.
- Noise keys were limited to 99,000 items per document; the limit is gone.
- `li + li` and `a ~ b` fataled with "Nesting level too deep" because of a loose
  `in_array()` comparison on objects that reference each other.
- `loadFile()` threw a `TypeError` (it passed an array to `file_get_contents()`).
- `createTextNode()` warned when the value was empty.
- Undefined variable `$debug_object` in `addClass()` and `hasClass()`.
- Undefined variable `$node` in `dump_node()`.
- `dump_html_tree()` called `dump()` with the wrong arguments.
- "Attempt to read property on null" when searching from a void element.
- Array-to-string warning in `dump_node()`.
- `find()` on a cleared node emitted warnings.

#### Wrong results

- Descendant selectors (`div p`, `tr td`, ...) returned wrong results for
  elements without a closing tag (`<li>`, `<p>`, `<td>`, `<tr>`, `<div/>`). Over
  the test corpus the original had about 48 spurious and 11 missing matches;
  2.0.0 matches the parsed tree on all of them.
- `a ~ b` also matched the starting node itself.
- Duplicate attributes (`<a href="x" href="y">`) shifted the spacing and quote
  style of every later attribute. An attribute after `=` and whitespace on a
  duplicate also leaked into the next attribute name. First value wins, as before.
- `removeAttribute()`, `unset($node->attr)` and attribute edits could produce
  broken markup (for example `value="new"q"`). Attributes are now removed
  together with their spacing and quote info.
- `hasAttribute()` and `isset()` returned `true` for removed attributes.
- `addClass()` / `removeClass()` could produce wrong class lists (`"1 x y"`).
- `appendChild()` did not detach the node from its old parent.
- Sibling navigation (`next_sibling()`, `prev_sibling()`, `first_child()`) broke
  after `remove()` / `removeChild()`.
- `removeChild()` left stale nodes behind and was O(n) per removed node.
- Comments inside a tag, such as `<a href="x"<!-- c -->`, came back as
  `no numeric noise key`.
- `file_get_html()` and `str_get_html()` ignored the `$defaultBRText` and
  `$defaultSpanText` arguments.
- The constructor applied `$forceTagsClosed` after parsing instead of before.
- The constructor ignored `$lowercase`, `$stripRN` and the other options when
  given a file path.
- `$dom->getElementsByTagName($name)` returned a single node (default index
  `-1`). It now returns an array like the node-level method.
- Class selectors compared with loose `in_array()`. They are now strict.
- The Cyrillic (CP1251) charset guess never triggered on modern PHP because
  mbstring now reports `Windows-1252`, not `CP1252`.
- Charset parsing took everything after `charset=`; it now reads only the
  charset name.
- If `iconv()` failed, `convert_text()` dropped the text. It now keeps it.

### Changed

- `$dom->getElementsByTagName()` default index is `null` (see above).
- `a ~ b` no longer includes the node itself.
- `removeAttribute()` and `unset()` remove the attribute completely. They no
  longer leave a `null` entry in `getAllAttributes()`.
- Attribute lookups with the magic getters treat a `null` value as unset.
- `parent()` / `appendChild()` do not add text nodes to `children`.
- `str_get_html('0')` and a page whose content is `"0"` now parse instead of
  returning `false`. `null`, `false` and `''` still return `false`.
- The constructor only treats a string as a file name if it is under 4096 bytes
  and contains no `<`. This avoids filesystem checks and `open_basedir` warnings
  on HTML strings.
- `load_file()` and `loadFile()` return the dom on success (still `false` on
  failure).
- `createElement()` and `createTextNode()` return `null` on failure.
- `addClass()` skips empty class names.
- `convert_text()` returns non-string values (such as valueless attributes)
  unchanged. It falls back to `mb_convert_encoding()` when `iconv` is missing.
- `is_utf8()` is stricter and follows the standard UTF-8 definition.
- `search_noise()` returns `null` explicitly when nothing matches.

### Internal

Only relevant if you subclass or read protected members.

- The per-node `__destruct()` is removed. `clear()` and the dom destructor
  handle cleanup.
- Noise keys are now `___noise___N__` and `$noise` is a plain list. Nested noise
  is resolved when stored, so restoring is a single non-recursive pass. Unknown
  keys are left as text.
- `remove_noise()` keeps its signature but uses `preg_replace_callback()`. If
  PCRE fails (backtrack or JIT limit) the document is left unchanged.
- `parse_attr()` returns `false` for a duplicate attribute.
- `link_nodes()` takes the node by value, not by reference.
- `seek()` is split into `candidates()` (which nodes to test) and `test()`
  (whether a node matches).
- Debug hooks still use the global `$debug_object`, but go through one static
  helper, `simple_html_dom_node::_dbg()`.

### Added

- Declarations are guarded (`function_exists()` / `class_exists()` and
  `defined()`), so loading the file twice, or alongside another copy in a
  WordPress plugin, no longer fatals with "cannot redeclare".
- PHP 8.4 compatibility: no dynamic property creation, no implicit nullable
  types, no `null` passed to string functions.

### Known limits

- Memory use is still driven by one PHP object per node with public arrays. Call
  `clear()` when finished with a document and size `memory_limit` for large pages.
- Nodes added with `appendChild()` are rendered but not found by `find()` on
  the receiving dom, as in 1.9.1.

## 1.9.1 (Rev. 291)

Original release by S.C. Chen, John Schlick, Rus Carroll and logmanoriginal
(contributors: Yousuke Kumakura, Vadim Voituk, Antcs; based on work by Jose
Solorzano). See https://sourceforge.net/projects/simplehtmldom/
