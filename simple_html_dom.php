<?php
/**
 * PHP Simple HTML DOM Parser
 * Version 2.0.0 (based on Rev. 1.9.1 (291)) - rewrite for PHP 8.4+.
 * Drop-in replacement: same functions, classes, methods, properties and constants.
 *
 * Original: http://sourceforge.net/projects/simplehtmldom/ (MIT License)
 * Acknowledge: Jose Solorzano (https://sourceforge.net/projects/php-html/)
 * Authors: S.C. Chen, John Schlick, Rus Carroll, logmanoriginal
 * Contributors: Yousuke Kumakura, Vadim Voituk, Antcs
 */

foreach (array(
	'HDOM_TYPE_ELEMENT' => 1, 'HDOM_TYPE_COMMENT' => 2, 'HDOM_TYPE_TEXT' => 3,
	'HDOM_TYPE_ENDTAG' => 4, 'HDOM_TYPE_ROOT' => 5, 'HDOM_TYPE_UNKNOWN' => 6,
	'HDOM_QUOTE_DOUBLE' => 0, 'HDOM_QUOTE_SINGLE' => 1, 'HDOM_QUOTE_NO' => 3,
	'HDOM_INFO_BEGIN' => 0, 'HDOM_INFO_END' => 1, 'HDOM_INFO_QUOTE' => 2,
	'HDOM_INFO_SPACE' => 3, 'HDOM_INFO_TEXT' => 4, 'HDOM_INFO_INNER' => 5,
	'HDOM_INFO_OUTER' => 6, 'HDOM_INFO_ENDSPACE' => 7, 'HDOM_SMARTY_AS_TEXT' => 1,
	'DEFAULT_TARGET_CHARSET' => 'UTF-8', 'DEFAULT_BR_TEXT' => "\r\n",
	'DEFAULT_SPAN_TEXT' => ' ', 'MAX_FILE_SIZE' => 20971520,
) as $k => $v) {
	defined($k) || define($k, $v);
}

if (!function_exists('file_get_html')) {
function file_get_html(
	$url,
	$use_include_path = false,
	$context = null,
	$offset = 0,
	$maxLen = -1,
	$lowercase = true,
	$forceTagsClosed = true,
	$target_charset = DEFAULT_TARGET_CHARSET,
	$stripRN = true,
	$defaultBRText = DEFAULT_BR_TEXT,
	$defaultSpanText = DEFAULT_SPAN_TEXT)
{
	if ($maxLen <= 0) { $maxLen = MAX_FILE_SIZE; }

	$contents = file_get_contents($url, $use_include_path, $context, $offset, $maxLen);

	if ($contents === false || $contents === '' || strlen($contents) > $maxLen) {
		return false;
	}

	$dom = new simple_html_dom(null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText);
	return $dom->load($contents, $lowercase, $stripRN, $defaultBRText, $defaultSpanText);
}
}

if (!function_exists('str_get_html')) {
function str_get_html(
	$str,
	$lowercase = true,
	$forceTagsClosed = true,
	$target_charset = DEFAULT_TARGET_CHARSET,
	$stripRN = true,
	$defaultBRText = DEFAULT_BR_TEXT,
	$defaultSpanText = DEFAULT_SPAN_TEXT)
{
	$str = (string)$str;

	if ($str === '' || strlen($str) > MAX_FILE_SIZE) {
		return false;
	}

	$dom = new simple_html_dom(null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText);
	return $dom->load($str, $lowercase, $stripRN, $defaultBRText, $defaultSpanText);
}
}

if (!function_exists('dump_html_tree')) {
function dump_html_tree($node, $show_attr = true, $deep = 0)
{
	$node->dump($show_attr, $deep);
}
}

if (!class_exists('simple_html_dom', false)) {

class simple_html_dom_node
{
	public $nodetype = HDOM_TYPE_TEXT;
	public $tag = 'text';
	public $attr = array();
	public $children = array();
	public $nodes = array();
	public $parent = null;
	public $_ = array();
	public $tag_start = 0;
	private $dom = null;

	function __construct($dom)
	{
		$this->dom = $dom;
		$dom->nodes[] = $this;
	}

	function __toString()
	{
		return $this->outertext();
	}

	/** Forwards to the optional global $debug_object (debug_log_entry / debug_log). */
	static function _dbg($level, $msg = null, $data = null)
	{
		$o = $GLOBALS['debug_object'] ?? null;

		if (!is_object($o)) { return; }

		if ($msg === null) {
			$o->debug_log_entry($level);
		} elseif ($data === null) {
			$o->debug_log($level, $msg);
		} else {
			$o->debug_log($level, $msg, $data);
		}
	}

	private function restore($text)
	{
		return $this->dom ? $this->dom->restore_noise($text) : $text;
	}

	function clear()
	{
		$this->dom = $this->parent = null;
		$this->nodes = $this->children = array();
	}

	function dump($show_attr = true, $depth = 0)
	{
		echo str_repeat("\t", $depth) . $this->tag;

		if ($show_attr && count($this->attr) > 0) {
			echo '(';
			foreach ($this->attr as $k => $v) {
				echo "[$k]=>\"$v\", ";
			}
			echo ')';
		}

		echo "\n";

		foreach ($this->nodes as $node) {
			$node->dump($show_attr, $depth + 1);
		}
	}

	function dump_node($echo = true)
	{
		$string = $this->tag;

		if (count($this->attr) > 0) {
			$string .= '(';
			foreach ($this->attr as $k => $v) {
				$string .= "[$k]=>\"$v\", ";
			}
			$string .= ')';
		}

		if (count($this->_) > 0) {
			$string .= ' $_ (';
			foreach ($this->_ as $k => $v) {
				if (is_array($v)) {
					$string .= "[$k]=>(";
					foreach ($v as $k2 => $v2) {
						$string .= '[' . $k2 . ']=>"' . (is_array($v2) ? implode(',', $v2) : $v2) . '", ';
					}
					$string .= ')';
				} else {
					$string .= "[$k]=>\"$v\", ";
				}
			}
			$string .= ')';
		}

		if (isset($this->text)) {
			$string .= " text: ({$this->text})";
		}

		$string .= ' HDOM_INNER_INFO: ';
		$string .= isset($this->_[HDOM_INFO_INNER]) ? "'" . $this->_[HDOM_INFO_INNER] . "'" : ' NULL ';
		$string .= ' children: ' . count($this->children)
			. ' nodes: ' . count($this->nodes)
			. ' tag_start: ' . $this->tag_start . "\n";

		if ($echo) {
			echo $string;
			return;
		}

		return $string;
	}

	function parent($parent = null)
	{
		if ($parent !== null) {
			// Detach from the previous parent first
			if ($old = $this->parent) {
				foreach (array('nodes', 'children') as $p) {
					$list = $old->$p;
					if (($i = array_search($this, $list, true)) !== false) {
						unset($list[$i]);
						$old->$p = array_values($list);
					}
				}
			}

			$this->parent = $parent;
			$parent->nodes[] = $this;

			if ($this->nodetype !== HDOM_TYPE_TEXT) {
				$parent->children[] = $this;
			}
		}

		return $this->parent;
	}

	function has_child()
	{
		return !empty($this->children);
	}

	function children($idx = -1)
	{
		return $idx === -1 ? $this->children : ($this->children[$idx] ?? null);
	}

	function first_child()
	{
		return $this->children[0] ?? null;
	}

	function last_child()
	{
		return $this->children ? end($this->children) : null;
	}

	function next_sibling()
	{
		if ($this->parent === null) { return null; }

		$idx = array_search($this, $this->parent->children, true);

		return $idx !== false ? ($this->parent->children[$idx + 1] ?? null) : null;
	}

	function prev_sibling()
	{
		if ($this->parent === null) { return null; }

		$idx = array_search($this, $this->parent->children, true);

		return ($idx !== false && $idx > 0) ? $this->parent->children[$idx - 1] : null;
	}

	function find_ancestor_tag($tag)
	{
		self::_dbg(1);

		$ancestor = $this->parent;

		while ($ancestor !== null) {
			self::_dbg(2, 'Current tag is: ' . $ancestor->tag);

			if ($ancestor->tag === $tag) { break; }

			$ancestor = $ancestor->parent;
		}

		return $ancestor;
	}

	function innertext()
	{
		if (isset($this->_[HDOM_INFO_INNER])) {
			return $this->_[HDOM_INFO_INNER];
		}

		if (isset($this->_[HDOM_INFO_TEXT])) {
			return $this->restore($this->_[HDOM_INFO_TEXT]);
		}

		$ret = '';

		foreach ($this->nodes as $n) {
			$ret .= $n->outertext();
		}

		return $ret;
	}

	function outertext()
	{
		self::_dbg(1, 'Innertext of tag: ' . $this->tag);

		if ($this->tag === 'root') {
			return $this->innertext();
		}

		if ($this->dom && $this->dom->callback !== null) {
			call_user_func($this->dom->callback, $this);
		}

		if (isset($this->_[HDOM_INFO_OUTER])) {
			return $this->_[HDOM_INFO_OUTER];
		}

		if (isset($this->_[HDOM_INFO_TEXT])) {
			return $this->restore($this->_[HDOM_INFO_TEXT]);
		}

		$ret = '';

		if ($this->dom && isset($this->_[HDOM_INFO_BEGIN]) && isset($this->dom->nodes[$this->_[HDOM_INFO_BEGIN]])) {
			$ret = $this->dom->nodes[$this->_[HDOM_INFO_BEGIN]]->makeup();
		}

		if (isset($this->_[HDOM_INFO_INNER])) {
			// <br> never outputs its inner info (it holds the default br text)
			if ($this->tag !== 'br') {
				$ret .= $this->_[HDOM_INFO_INNER];
			}
		} else {
			foreach ($this->nodes as $n) {
				$ret .= $this->convert_text($n->outertext());
			}
		}

		if (!empty($this->_[HDOM_INFO_END])) {
			$ret .= '</' . $this->tag . '>';
		}

		return $ret;
	}

	function text()
	{
		if (isset($this->_[HDOM_INFO_INNER])) {
			return $this->_[HDOM_INFO_INNER];
		}

		switch ($this->nodetype) {
			case HDOM_TYPE_TEXT: return $this->restore($this->_[HDOM_INFO_TEXT] ?? '');
			case HDOM_TYPE_COMMENT:
			case HDOM_TYPE_UNKNOWN: return '';
		}

		if (strcasecmp($this->tag, 'script') === 0 || strcasecmp($this->tag, 'style') === 0) {
			return '';
		}

		$ret = '';

		foreach ($this->nodes as $n) {
			// Start paragraph after a blank line
			if ($n->tag === 'p') {
				$ret = trim($ret) . "\n\n";
			}

			$ret .= $this->convert_text($n->text());

			// Keep consecutive spans from running into each other
			if ($n->tag === 'span') {
				$ret .= $this->dom?->default_span_text;
			}
		}

		return $ret;
	}

	function xmltext()
	{
		return str_replace(']]>', '', str_ireplace('<![CDATA[', '', $this->innertext()));
	}

	function makeup()
	{
		// text, comment, unknown
		if (isset($this->_[HDOM_INFO_TEXT])) {
			return $this->restore($this->_[HDOM_INFO_TEXT]);
		}

		$ret = '<' . $this->tag;
		$i = -1;

		foreach ($this->attr as $key => $val) {
			++$i;

			// skip removed attribute
			if ($val === null || $val === false) { continue; }

			$sp = $this->_[HDOM_INFO_SPACE][$i] ?? array(' ', '', '');
			$ret .= $sp[0];

			// no value attr: nowrap, checked, selected...
			if ($val === true) {
				$ret .= $key;
				continue;
			}

			switch ($this->_[HDOM_INFO_QUOTE][$i] ?? HDOM_QUOTE_DOUBLE) {
				case HDOM_QUOTE_DOUBLE: $q = '"'; break;
				case HDOM_QUOTE_SINGLE: $q = "'"; break;
				default: $q = '';
			}

			$ret .= $key . $sp[1] . '=' . $sp[2] . $q . $val . $q;
		}

		return $this->restore($ret) . ($this->_[HDOM_INFO_ENDSPACE] ?? '') . '>';
	}

	function find($selector, $idx = null, $lowercase = false)
	{
		if (!$this->dom || !isset($this->_[HDOM_INFO_BEGIN])) { return array(); }

		if (!($selectors = $this->parse_selector($selector))) { return array(); }

		$dom = $this->dom;
		$found_keys = array();

		foreach ($selectors as $sel) {
			if (!$sel) { return array(); }

			$head = array($this->_[HDOM_INFO_BEGIN] => 1);
			$cmd = ' '; // Combinator

			// handle descendant selectors, no recursive!
			foreach ($sel as $level) {
				$ret = array();

				foreach ($head as $k => $v) {
					$n = ($k === -1) ? $dom->root : ($dom->nodes[$k] ?? null);

					if ($n) { $n->seek($level, $ret, $cmd, $lowercase); }
				}

				$head = $ret;
				$cmd = $level[4]; // Next combinator
			}

			$found_keys += $head;
		}

		ksort($found_keys);

		$found = array();
		foreach ($found_keys as $k => $v) {
			$found[] = $dom->nodes[$k];
		}

		if ($idx === null) { return $found; }
		if ($idx < 0) { $idx = count($found) + $idx; }

		return $found[$idx] ?? null;
	}

	/** Yields candidate nodes (dom index => node) for the given combinator. */
	private function candidates($cmd)
	{
		if ($cmd === ' ') { // Descendant
			$nodes = $this->dom->nodes;
			$i = $this->_[HDOM_INFO_BEGIN] + 1;

			// Closed element: its subtree is the index range up to the end tag
			for ($end = (int)($this->_[HDOM_INFO_END] ?? 0); $i < $end; ++$i) {
				if (isset($nodes[$i])) { yield $i => $nodes[$i]; }
			}

			// Beyond that (or with no end tag: void, "<div/>", implicitly closed)
			// the subtree is whatever follows while the nodes descend from this one
			for ($last = array_key_last($nodes); $i <= $last; ++$i) {
				if (!isset($nodes[$i])) { continue; }

				for ($p = $nodes[$i]->parent; $p && $p !== $this; $p = $p->parent);

				if (!$p) { return; }

				yield $i => $nodes[$i];
			}

			return;
		}

		$list = array();

		if ($cmd === '>') { // Child
			$list = $this->children;
		} elseif (($cmd === '+' || $cmd === '~') && $this->parent) { // Next / subsequent sibling
			$seen = false;

			foreach ($this->parent->children as $c) {
				if ($seen) {
					$list[] = $c;
					if ($cmd === '+') { break; }
				} elseif ($c === $this) {
					$seen = true;
				}
			}
		}

		foreach ($list as $c) {
			yield ($c->_[HDOM_INFO_BEGIN] ?? 0) => $c;
		}
	}

	protected function seek($selector, &$ret, $parent_cmd, $lowercase = false)
	{
		self::_dbg(1);

		list($tag, $id, $class, $attributes) = $selector;

		foreach ($this->candidates($parent_cmd) as $key => $node) {
			// Skip root nodes
			if (!$node->parent) { continue; }

			// Handle 'text' selector
			if ($tag === 'text' && $node->tag === 'text') {
				$ret[$key] = 1;
				continue;
			}

			// Text nodes are not child nodes
			if ($node->nodetype !== HDOM_TYPE_TEXT
				&& $this->test($node, $tag, $id, $class, $attributes, $lowercase)) {
				$ret[$node->_[HDOM_INFO_BEGIN]] = 1;
			}
		}

		self::_dbg(1, 'EXIT - ret: ', $ret);
	}

	private function test($node, $tag, $id, $class, $attributes, $lowercase)
	{
		if ($tag !== '' && $tag !== '*' && $tag !== $node->tag) { return false; }

		if ($id !== '') {
			// Only consider the first ID (as browsers do)
			if (!isset($node->attr['id']) || $id !== explode(' ', trim($node->attr['id']))[0]) {
				return false;
			}
		}

		if (is_array($class) && $class) {
			if (!isset($node->attr['class'])) { return false; }

			$node_classes = explode(' ', $node->attr['class']);

			if ($lowercase) {
				$node_classes = array_map('strtolower', $node_classes);
			}

			foreach ($class as $c) {
				if (!in_array($c, $node_classes, true)) { return false; }
			}
		}

		if (is_array($attributes) && $attributes) {
			foreach ($attributes as list($name, $expr, $val, $inv, $case)) {
				// Indexing attribute (i.e. "[2]"): XPath-like n-th element of its kind
				if (is_numeric($name) && $expr === '' && $val === '') {
					$count = 0;

					foreach ($node->parent->children as $c) {
						if ($c->tag === $node->tag) { ++$count; }
						if ($c === $node) { break; }
					}

					if ($count === (int)$name) { continue; }
				}

				if ($inv) { // Attribute should NOT be set
					if (isset($node->attr[$name])) { return false; }
				} elseif ($name !== 'plaintext' && !isset($node->attr[$name])) {
					return false;
				}

				if ($expr === '') { continue; }

				$value = ($name === 'plaintext') ? $node->text() : ($node->attr[$name] ?? '');

				self::_dbg(2, 'testing node: ' . $node->tag . ' for attribute: ' . $name . $expr . $val . ' where nodes value is: ' . $value);

				$check = $lowercase
					? $this->match($expr, strtolower($val), strtolower($value), $case)
					: $this->match($expr, $val, $value, $case);

				self::_dbg(2, 'after match: ' . ($check ? 'true' : 'false'));

				if (!$check) { return false; }
			}
		}

		return true;
	}

	protected function match($exp, $pattern, $value, $case_sensitivity)
	{
		self::_dbg(1);

		if ($case_sensitivity === 'i') {
			$pattern = strtolower($pattern);
			$value = strtolower($value);
		}

		switch ($exp) {
			case '=': return $value === $pattern;
			case '!=': return $value !== $pattern;
			case '^=':
			case '|=': return str_starts_with($value, $pattern);
			case '$=': return str_ends_with($value, $pattern);
			case '*=': return str_contains($value, $pattern);
			case '~=': return in_array($pattern, explode(' ', trim($value)), true);
		}

		return false;
	}

	protected function parse_selector($selector_string)
	{
		self::_dbg(1);

		/**
		 * Selector pattern, modified from mootools. Groups:
		 * [1] tag name, [2] id, [3] classes (with dots), [4] attributes, [5] separator
		 * Attribute names may contain colons and start with "@" (not captured).
		 */
		$pattern = "/([\w:\*-]*)(?:\#([\w-]+))?(?:|\.([\w\.-]+))?((?:\[@?(?:!?[\w:-]+)(?:(?:[!*^$|~]?=)[\"']?(?:.*?)[\"']?)?(?:\s*?(?:[iIsS])?)?\])+)?([\/, >+~]+)/is";

		$matches = array();
		preg_match_all($pattern, trim($selector_string) . ' ', $matches, PREG_SET_ORDER);

		self::_dbg(2, 'Matches Array: ', $matches);

		$selectors = array();
		$result = array();

		foreach ($matches as $m) {
			$m[0] = trim($m[0]);

			// Skip NoOps
			if ($m[0] === '' || $m[0] === '/' || $m[0] === '//') { continue; }

			if ($this->dom->lowercase) {
				$m[1] = strtolower($m[1]);
			}

			// Extract classes
			if ($m[3] !== '') { $m[3] = explode('.', $m[3]); }

			/* Extract attributes: [1] name, [2] expression, [3] value, [4] case flag.
			 * Names can be negated with a "!" prefix. */
			if ($m[4] !== '') {
				$attributes = array();

				preg_match_all(
					"/\[@?(!?[\w:-]+)(?:([!*^$|~]?=)[\"']?(.*?)[\"']?)?(?:\s+?([iIsS])?)?\]/is",
					trim($m[4]),
					$attributes,
					PREG_SET_ORDER
				);

				$m[4] = array();

				foreach ($attributes as $att) {
					if (trim($att[0]) === '') { continue; }

					$inverted = (isset($att[1][0]) && $att[1][0] === '!');
					$m[4][] = array(
						$inverted ? substr($att[1], 1) : $att[1], // Name
						$att[2] ?? '', // Expression
						$att[3] ?? '', // Value
						$inverted, // Inverted flag
						strtolower($att[4] ?? ''), // Case sensitivity
					);
				}
			}

			// Sanitize separator
			$m[5] = ($m[5] !== '' && trim($m[5]) === '') ? ' ' : trim($m[5]);

			// A comma ends the current selector of a selector list
			if ($is_list = ($m[5] === ',')) { $m[5] = ''; }

			array_shift($m);
			$result[] = $m;

			if ($is_list) {
				$selectors[] = $result;
				$result = array();
			}
		}

		if ($result) { $selectors[] = $result; }

		return $selectors;
	}

	function __get($name)
	{
		if (isset($this->attr[$name])) {
			return $this->convert_text($this->attr[$name]);
		}

		switch ($name) {
			case 'outertext': return $this->outertext();
			case 'innertext': return $this->innertext();
			case 'plaintext': return $this->text();
			case 'xmltext': return $this->xmltext();
			default: return array_key_exists($name, $this->attr);
		}
	}

	function __set($name, $value)
	{
		self::_dbg(1);

		switch ($name) {
			case 'outertext': return $this->_[HDOM_INFO_OUTER] = $value;
			case 'innertext':
				if (isset($this->_[HDOM_INFO_TEXT])) {
					return $this->_[HDOM_INFO_TEXT] = $value;
				}
				return $this->_[HDOM_INFO_INNER] = $value;
		}

		if (!array_key_exists($name, $this->attr)) {
			$this->_[HDOM_INFO_SPACE][] = array(' ', '', '');
			$this->_[HDOM_INFO_QUOTE][] = HDOM_QUOTE_DOUBLE;
		}

		$this->attr[$name] = $value;
	}

	function __isset($name)
	{
		// no value attr (nowrap, checked, selected...) are stored as true
		return $name === 'outertext' || $name === 'innertext' || $name === 'plaintext' || isset($this->attr[$name]);
	}

	function __unset($name)
	{
		if (!array_key_exists($name, $this->attr)) { return; }

		// Drop the attribute together with its spacing/quote info to keep them aligned
		$i = 0;
		foreach ($this->attr as $k => $v) {
			if ((string)$k === (string)$name) { break; }
			++$i;
		}

		unset($this->attr[$name]);

		foreach (array(HDOM_INFO_SPACE, HDOM_INFO_QUOTE) as $info) {
			if (isset($this->_[$info][$i])) {
				array_splice($this->_[$info], $i, 1);
			}
		}
	}

	function convert_text($text)
	{
		self::_dbg(1);

		if (!is_string($text) || !$this->dom) { return $text; }

		$from = strtoupper($this->dom->_charset);
		$to = strtoupper($this->dom->_target_charset);

		self::_dbg(3, 'source charset: ' . $from . ' target charset: ' . $to);

		if ($from !== '' && $to !== '' && $from !== $to) {
			// The reported encoding could be wrong and the text is actually already UTF-8
			if (!($to === 'UTF-8' && self::is_utf8($text))) {
				if (function_exists('iconv')) {
					$converted = @iconv($from, $to, $text);
				} else {
					$converted = function_exists('mb_convert_encoding') ? @mb_convert_encoding($text, $to, $from) : false;
				}

				if ($converted !== false) { $text = $converted; }
			}
		}

		// Make sure we don't output a BOM in UTF-8 text
		if ($to === 'UTF-8') {
			if (str_starts_with($text, "\xef\xbb\xbf")) { $text = substr($text, 3); }
			if (str_ends_with($text, "\xef\xbb\xbf")) { $text = substr($text, 0, -3); }
		}

		return $text;
	}

	static function is_utf8($str)
	{
		return (bool)preg_match('//u', (string)$str);
	}

	function get_display_size()
	{
		if ($this->tag !== 'img') { return false; }

		$size = array('width' => $this->attr['width'] ?? -1, 'height' => $this->attr['height'] ?? -1);

		// Look for an inline style (only integer "px" values are considered)
		if (isset($this->attr['style'])) {
			$style = array();

			preg_match_all('/([\w-]+)\s*:\s*([^;]+)\s*;?/', $this->attr['style'], $matches, PREG_SET_ORDER);

			foreach ($matches as $match) {
				$style[$match[1]] = $match[2];
			}

			foreach ($size as $k => $v) {
				if ($v == -1
					&& isset($style[$k])
					&& strtolower(substr($style[$k], -2)) === 'px'
					&& filter_var($px = substr($style[$k], 0, -2), FILTER_VALIDATE_INT)) {
					$size[$k] = $px;
				}
			}
		}

		return array('height' => $size['height'], 'width' => $size['width']);
	}

	function save($filepath = '')
	{
		$ret = $this->outertext();

		if ($filepath !== '') {
			file_put_contents($filepath, $ret, LOCK_EX);
		}

		return $ret;
	}

	function addClass($class)
	{
		if (is_string($class)) {
			$class = explode(' ', $class);
		}

		if (!is_array($class)) {
			self::_dbg(2, 'Invalid type: ', gettype($class));
			return;
		}

		foreach ($class as $c) {
			if ($c === '' || $this->hasClass($c)) { continue; }

			$this->class = isset($this->attr['class']) ? $this->attr['class'] . ' ' . $c : $c;
		}
	}

	function hasClass($class)
	{
		if (!is_string($class)) {
			self::_dbg(2, 'Invalid type: ', gettype($class));
			return false;
		}

		return isset($this->attr['class']) && in_array($class, explode(' ', $this->attr['class']), true);
	}

	function removeClass($class = null)
	{
		if (!isset($this->attr['class'])) { return; }

		if ($class === null) {
			$this->__unset('class');
			return;
		}

		if (is_string($class)) {
			$class = explode(' ', $class);
		}

		if (is_array($class)) {
			$class = array_diff(explode(' ', $this->attr['class']), $class);

			if (empty($class)) {
				$this->__unset('class');
			} else {
				$this->attr['class'] = implode(' ', $class);
			}
		}
	}

	function getAllAttributes()
	{
		return $this->attr;
	}

	function getAttribute($name)
	{
		return $this->__get($name);
	}

	function setAttribute($name, $value)
	{
		$this->__set($name, $value);
	}

	function hasAttribute($name)
	{
		return $this->__isset($name);
	}

	function removeAttribute($name)
	{
		$this->__unset($name);
	}

	function remove()
	{
		if ($this->parent) {
			$this->parent->removeChild($this);
		}
	}

	function removeChild($node)
	{
		$dom = $this->dom;
		$nidx = array_search($node, $this->nodes, true);
		$cidx = array_search($node, $this->children, true);
		$begin = $node->_[HDOM_INFO_BEGIN] ?? null;

		if (!$dom || $nidx === false || $cidx === false || $begin === null || ($dom->nodes[$begin] ?? null) !== $node) {
			return;
		}

		// The subtree is stored contiguously (document order) in the dom's node list
		$gone = array($node);
		unset($dom->nodes[$begin]);

		for ($i = $begin + 1, $last = array_key_last($dom->nodes); $i <= $last; ++$i) {
			if (!isset($dom->nodes[$i])) { continue; }

			$x = $dom->nodes[$i];

			for ($p = $x->parent; $p && $p !== $node; $p = $p->parent);

			if (!$p) { break; }

			$gone[] = $x;
			unset($dom->nodes[$i]);
		}

		unset($this->nodes[$nidx], $this->children[$cidx]);
		$this->nodes = array_values($this->nodes);
		$this->children = array_values($this->children);

		foreach ($gone as $x) {
			$x->clear();
		}
	}

	function getElementById($id)
	{
		return $this->find("#$id", 0);
	}

	function getElementsById($id, $idx = null)
	{
		return $this->find("#$id", $idx);
	}

	function getElementByTagName($name)
	{
		return $this->find($name, 0);
	}

	function getElementsByTagName($name, $idx = null)
	{
		return $this->find($name, $idx);
	}

	function parentNode()
	{
		return $this->parent();
	}

	function childNodes($idx = -1)
	{
		return $this->children($idx);
	}

	function firstChild()
	{
		return $this->first_child();
	}

	function lastChild()
	{
		return $this->last_child();
	}

	function nextSibling()
	{
		return $this->next_sibling();
	}

	function previousSibling()
	{
		return $this->prev_sibling();
	}

	function hasChildNodes()
	{
		return $this->has_child();
	}

	function nodeName()
	{
		return $this->tag;
	}

	function appendChild($node)
	{
		$node->parent($this);
		return $node;
	}
}

class simple_html_dom
{
	public $root = null;
	public $nodes = array();
	public $callback = null;
	public $lowercase = false;
	public $original_size;
	public $size;

	protected $pos;
	protected $doc = '';
	protected $char;

	protected $cursor;
	protected $parent;
	protected $noise = array();
	protected $token_blank = " \t\r\n";
	protected $token_equal = ' =/>';
	protected $token_slash = " />\r\n\t";
	protected $token_attr = ' >';

	public $_charset = '';
	public $_target_charset = '';

	protected $default_br_text = '';

	public $default_span_text = '';

	private static $shared = array();

	protected $self_closing_tags = array(
		'area' => 1, 'base' => 1, 'br' => 1, 'col' => 1, 'embed' => 1, 'hr' => 1, 'img' => 1,
		'input' => 1, 'link' => 1, 'meta' => 1, 'param' => 1, 'source' => 1, 'track' => 1, 'wbr' => 1
	);
	protected $block_tags = array(
		'body' => 1, 'div' => 1, 'form' => 1, 'root' => 1, 'span' => 1, 'table' => 1
	);
	protected $optional_closing_tags = array(
		// 'b' and 'dl' are not optional per spec, kept for compatibility
		'b' => array('b' => 1),
		'dd' => array('dd' => 1, 'dt' => 1),
		'dl' => array('dd' => 1, 'dt' => 1),
		'dt' => array('dd' => 1, 'dt' => 1),
		'li' => array('li' => 1),
		'optgroup' => array('optgroup' => 1, 'option' => 1),
		'option' => array('optgroup' => 1, 'option' => 1),
		'p' => array('p' => 1),
		'rp' => array('rp' => 1, 'rt' => 1),
		'rt' => array('rp' => 1, 'rt' => 1),
		'td' => array('td' => 1, 'th' => 1),
		'th' => array('td' => 1, 'th' => 1),
		'tr' => array('td' => 1, 'th' => 1, 'tr' => 1),
	);

	function __construct(
		$str = null,
		$lowercase = true,
		$forceTagsClosed = true,
		$target_charset = DEFAULT_TARGET_CHARSET,
		$stripRN = true,
		$defaultBRText = DEFAULT_BR_TEXT,
		$defaultSpanText = DEFAULT_SPAN_TEXT,
		$options = 0)
	{
		// Forcing tags to be closed implies that we don't trust the html, but
		// it can lead to parsing errors if we SHOULD trust the html.
		if (!$forceTagsClosed) {
			$this->optional_closing_tags = array();
		}

		$this->_target_charset = $target_charset;

		if ($str) {
			// Only plain strings that look like a URL or file name are fetched
			if (preg_match('/^http:\/\//i', $str)
				|| (strlen($str) < 4096 && strpos($str, '<') === false && is_file($str))) {
				$str = file_get_contents($str);
			}

			if ($str !== false && $str !== '') {
				$this->load($str, $lowercase, $stripRN, $defaultBRText, $defaultSpanText, $options);
			}
		}
	}

	function __destruct()
	{
		$this->clear();
	}

	function load(
		$str,
		$lowercase = true,
		$stripRN = true,
		$defaultBRText = DEFAULT_BR_TEXT,
		$defaultSpanText = DEFAULT_SPAN_TEXT,
		$options = 0)
	{
		$this->prepare((string)$str, $lowercase, $defaultBRText, $defaultSpanText);

		// Script tags removal precedes style tag removal.
		$this->remove_noise("'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is");
		$this->remove_noise("'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is");

		// strip out the \r \n's if we are told to.
		if ($stripRN) {
			$this->doc = strtr($this->doc, "\r\n", '  ');
			$this->size = strlen($this->doc);
		}

		$this->remove_noise("'<!\[CDATA\[(.*?)\]\]>'is", true); // cdata
		$this->remove_noise("'<!--(.*?)-->'is"); // comments
		$this->remove_noise("'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is");
		$this->remove_noise("'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is");
		$this->remove_noise("'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is"); // preformatted
		$this->remove_noise("'(<\?)(.*?)(\?>)'s", true); // server side scripts

		if ($options & HDOM_SMARTY_AS_TEXT) {
			$this->remove_noise("'(\{\w)(.*?)(\})'s", true);
		}

		$this->parse();
		$this->root->_[HDOM_INFO_END] = $this->cursor;
		$this->parse_charset();

		// The source is no longer needed: node text was copied out of it
		$this->doc = '';

		// make load function chainable
		return $this;
	}

	function load_file(...$args)
	{
		if (($doc = file_get_contents(...$args)) === false) {
			return false;
		}

		return $this->load($doc, true);
	}

	function set_callback($function_name)
	{
		$this->callback = $function_name;
	}

	function remove_callback()
	{
		$this->callback = null;
	}

	function save($filepath = '')
	{
		$ret = $this->root->innertext();

		if ($filepath !== '') { file_put_contents($filepath, $ret, LOCK_EX); }

		return $ret;
	}

	function find($selector, $idx = null, $lowercase = false)
	{
		return $this->root->find($selector, $idx, $lowercase);
	}

	/** Breaks all reference cycles and releases the nodes. */
	function clear()
	{
		foreach ($this->nodes as $n) {
			$n->clear();
		}

		$this->nodes = array();
		$this->root = $this->parent = null;
		$this->doc = '';
		$this->noise = array();
	}

	function dump($show_attr = true)
	{
		$this->root->dump($show_attr);
	}

	protected function prepare(
		$str,
		$lowercase = true,
		$defaultBRText = DEFAULT_BR_TEXT,
		$defaultSpanText = DEFAULT_SPAN_TEXT)
	{
		$this->clear();

		$this->doc = trim($str);
		$this->size = $this->original_size = strlen($this->doc);
		$this->pos = 0;
		$this->cursor = 1;
		$this->lowercase = $lowercase;
		$this->default_br_text = $defaultBRText;
		$this->default_span_text = $defaultSpanText;
		$this->root = new simple_html_dom_node($this);
		$this->root->tag = 'root';
		$this->root->_[HDOM_INFO_BEGIN] = -1;
		$this->root->nodetype = HDOM_TYPE_ROOT;
		$this->parent = $this->root;
		$this->char = $this->size > 0 ? $this->doc[0] : null;
	}

	protected function parse()
	{
		while (true) {
			// Read next tag if there is no text between current position and the next opening tag.
			if (($s = $this->copy_until_char('<')) === '') {
				if ($this->read_tag()) {
					continue;
				}

				return true;
			}

			// Add a text node for text between tags
			$node = new simple_html_dom_node($this);
			++$this->cursor;
			$node->_[HDOM_INFO_TEXT] = $s;
			$this->link_nodes($node, false);
		}
	}

	protected function parse_charset()
	{
		$charset = null;

		if (function_exists('get_last_retrieve_url_contents_content_type')) {
			if (preg_match('/charset\s*=\s*["\']?([\w.:+-]+)/i', get_last_retrieve_url_contents_content_type(), $matches)) {
				$charset = $matches[1];
				simple_html_dom_node::_dbg(2, 'header content-type found charset of: ' . $charset);
			}
		}

		if (empty($charset)) {
			$el = $this->root->find('meta[http-equiv=Content-Type]', 0, true);

			if (!empty($el) && !empty($fullvalue = $el->content)) {
				simple_html_dom_node::_dbg(2, 'meta content-type tag found' . $fullvalue);

				// A meta tag without a character set is typically ISO-8859-1
				$charset = preg_match('/charset\s*=\s*["\']?([\w.:+-]+)/i', $fullvalue, $matches) ? $matches[1] : 'ISO-8859-1';
			}
		}

		if (empty($charset) && ($meta = $this->root->find('meta[charset]', 0))) {
			$charset = $meta->charset;
			simple_html_dom_node::_dbg(2, 'meta charset: ' . $charset);
		}

		if (empty($charset) && function_exists('mb_detect_encoding')) {
			// mb_detect_encoding() cannot tell single-byte charsets apart; only
			// distinguish UTF-8 from CP1252/ISO-8859-1 to stay compatible.
			$encoding = mb_detect_encoding($this->doc, array('UTF-8', 'CP1252', 'ISO-8859-1'));

			// CP1251/ISO-8859-5 is detected as CP1252/ISO-8859-1; bytes undefined in
			// CP1252 give it away.
			if (in_array(strtoupper((string)$encoding), array('CP1252', 'WINDOWS-1252', 'ISO-8859-1'), true) && preg_match('/[\x81\x8d\x8f\x90\x9d]/', $this->doc)) {
				$encoding = 'CP1251';
			}

			if ($encoding !== false) {
				$charset = $encoding;
				simple_html_dom_node::_dbg(2, 'mb_detect: ' . $charset);
			}
		}

		// Assume UTF-8 as it is the most likely charset
		if (empty($charset)) {
			$charset = 'UTF-8';
			simple_html_dom_node::_dbg(2, 'No match found, assume ' . $charset);
		}

		// CP1252 is a superset of its subsets
		if (in_array(strtolower($charset), array('iso-8859-1', 'latin1', 'latin-1'), true)) {
			$charset = 'CP1252';
			simple_html_dom_node::_dbg(2, 'replacing with CP1252 as its a superset');
		}

		simple_html_dom_node::_dbg(1, 'EXIT - ' . $charset);

		return $this->_charset = $charset;
	}

	/**
	 * Per-attribute spacing/quote info is almost always identical between tags.
	 * Returning one shared (copy-on-write) array for equal values saves a lot of memory.
	 */
	private static function share($info)
	{
		$k = json_encode($info);

		if (!isset(self::$shared[$k])) {
			if (count(self::$shared) > 2000) { self::$shared = array(); }

			self::$shared[$k] = $info;
		}

		return self::$shared[$k];
	}

	/** Moves to the next character of the document. */
	private function step()
	{
		$this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null;
	}

	protected function read_tag()
	{
		// Set end position if no further tags found
		if ($this->char !== '<') {
			$this->root->_[HDOM_INFO_END] = $this->cursor;
			return false;
		}

		$begin_tag_pos = $this->pos;
		$this->step();

		// end tag
		if ($this->char === '/') {
			$this->step();

			// Skip whitespace in end tags (i.e. in "</   html>")
			$this->skip($this->token_blank);
			$tag = $this->copy_until_char('>');

			// Skip attributes in end tags
			if (($pos = strpos($tag, ' ')) !== false) {
				$tag = substr($tag, 0, $pos);
			}

			$parent_lower = strtolower($this->parent->tag);
			$tag_lower = strtolower($tag);

			// The end tag is supposed to close the parent tag. Handle situations when it doesn't
			if ($parent_lower !== $tag_lower) {
				$optional = isset($this->optional_closing_tags[$parent_lower]);

				if (isset($this->block_tags[$tag_lower]) && ($optional || $this->parent->parent)) {
					// The current tag is a block tag, so it may close an ancestor
					$this->parent->_[HDOM_INFO_END] = 0;
					$org_parent = $this->parent;

					// Traverse ancestors to find a matching opening tag. Stop at root node
					while ($this->parent->parent && strtolower($this->parent->tag) !== $tag_lower) {
						$this->parent = $this->parent->parent;
					}

					// If we don't have a match add current tag as text node
					if (strtolower($this->parent->tag) !== $tag_lower) {
						$this->parent = $org_parent;

						if ($optional && $this->parent->parent) {
							$this->parent = $this->parent->parent;
						}

						$this->parent->_[HDOM_INFO_END] = $this->cursor;
						return $this->as_text_node($tag);
					}
				} elseif ($this->parent->parent && strtolower($this->parent->parent->tag) === $tag_lower) {
					// Grandparent exists and current tag closes it
					$this->parent->_[HDOM_INFO_END] = 0;
					$this->parent = $this->parent->parent;
				} else { // Random tag, add as text node
					return $this->as_text_node($tag);
				}
			}

			// Set end position of parent tag to current cursor position
			$this->parent->_[HDOM_INFO_END] = $this->cursor;

			if ($this->parent->parent) {
				$this->parent = $this->parent->parent;
			}

			$this->step();
			return true;
		}

		// start tag
		$node = new simple_html_dom_node($this);
		$node->_[HDOM_INFO_BEGIN] = $this->cursor;
		++$this->cursor;
		$tag = $this->copy_until($this->token_slash); // Get tag name
		$node->tag_start = $begin_tag_pos;

		// doctype, cdata & comments: <!DOCTYPE html> <![CDATA[ ... ]]> <!-- Comment -->
		if (isset($tag[0]) && $tag[0] === '!') {
			$node->_[HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until_char('>');

			if (isset($tag[2]) && $tag[1] === '-' && $tag[2] === '-') { // Comment ("<!--")
				$node->nodetype = HDOM_TYPE_COMMENT;
				$node->tag = 'comment';
			} else { // Could be doctype or CDATA but we don't care
				$node->nodetype = HDOM_TYPE_UNKNOWN;
				$node->tag = 'unknown';
			}

			if ($this->char === '>') { $node->_[HDOM_INFO_TEXT] .= '>'; }

			$this->link_nodes($node, true);
			$this->step();
			return true;
		}

		// The start tag cannot contain another start tag, if so add as text (i.e. "<<html>")
		if (strpos($tag, '<') !== false) {
			$node->_[HDOM_INFO_TEXT] = '<' . substr($tag, 0, -1);
			$this->link_nodes($node, false);
			$this->char = $this->doc[--$this->pos]; // prev
			return true;
		}

		// Handle invalid tag names (i.e. "<html#doc>")
		if (!preg_match('/^\w[\w:-]*$/', $tag)) {
			$node->_[HDOM_INFO_TEXT] = '<' . $tag . $this->copy_until('<>');

			// Next char is the beginning of a new tag, don't touch it.
			if ($this->char === '<') {
				$this->link_nodes($node, false);
				return true;
			}

			// Next char closes current tag, add and be done with it.
			if ($this->char === '>') { $node->_[HDOM_INFO_TEXT] .= '>'; }

			$this->link_nodes($node, false);
			$this->step();
			return true;
		}

		// begin tag, add new node
		$node->nodetype = HDOM_TYPE_ELEMENT;
		$tag_lower = strtolower($tag);
		$node->tag = $this->lowercase ? $tag_lower : $tag;

		// handle optional closing tags: close all open ancestors this tag implies
		if (isset($this->optional_closing_tags[$tag_lower])) {
			while (isset($this->optional_closing_tags[$tag_lower][strtolower($this->parent->tag)])) {
				$this->parent->_[HDOM_INFO_END] = 0;
				$this->parent = $this->parent->parent;
			}
		}

		$guard = 0; // prevent infinity loop

		// [0] Space between tag and first attribute
		$space = array($this->copy_skip($this->token_blank), '', '');

		// attributes
		do {
			// Everything until the first equal sign should be the attribute name
			$name = $this->copy_until($this->token_equal);

			if ($name === '' && $this->char !== null && $space[0] === '') {
				break;
			}

			if ($guard === $this->pos) { // Escape infinite loop
				$this->step();
				continue;
			}

			$guard = $this->pos;

			// handle endless '<': out of bounds before the tag ended
			if ($this->pos >= $this->size - 1 && $this->char !== '>') {
				$node->nodetype = HDOM_TYPE_TEXT;
				$node->_[HDOM_INFO_END] = 0;
				$node->_[HDOM_INFO_TEXT] = '<' . $tag . $space[0] . $name;
				$node->tag = 'text';
				$this->link_nodes($node, false);
				return true;
			}

			// handle mismatch '<': attributes cannot start after opening tag
			if ($this->doc[$this->pos - 1] === '<') {
				$node->nodetype = HDOM_TYPE_TEXT;
				$node->tag = 'text';
				$node->attr = array();
				$node->_[HDOM_INFO_END] = 0;
				$node->_[HDOM_INFO_TEXT] = substr($this->doc, $begin_tag_pos, $this->pos - $begin_tag_pos - 1);
				$this->pos -= 2;
				$this->step();
				$this->link_nodes($node, false);
				return true;
			}

			if ($name === '/' || $name === '') { // no more attributes
				break;
			}

			// [1] Whitespace after attribute name
			$space[1] = $this->copy_skip($this->token_blank);

			$name = $this->restore_noise($name); // might be a noisy name

			if ($this->lowercase) { $name = strtolower($name); }

			if ($this->char === '=') { // attribute with value
				$this->step();
				$added = $this->parse_attr($node, $name, $space); // get attribute value
			} else {
				// no value attr: nowrap, checked selected...
				if ($added = !isset($node->attr[$name])) {
					$node->_[HDOM_INFO_QUOTE][] = HDOM_QUOTE_NO;
					$node->attr[$name] = true;
				}

				if ($this->char != '>') { $this->char = $this->doc[--$this->pos]; } // prev
			}

			// a duplicate attribute is dropped (first one wins)
			if ($added) {
				$node->_[HDOM_INFO_SPACE][] = $space;
			}

			// prepare for next attribute
			$space = array($this->copy_skip($this->token_blank), '', '');
		} while ($this->char !== '>' && $this->char !== '/'); // go until the tag ended

		if (isset($node->_[HDOM_INFO_SPACE])) {
			$node->_[HDOM_INFO_SPACE] = self::share($node->_[HDOM_INFO_SPACE]);
			$node->_[HDOM_INFO_QUOTE] = self::share($node->_[HDOM_INFO_QUOTE]);
		}

		$this->link_nodes($node, true);
		$node->_[HDOM_INFO_ENDSPACE] = $space[0];

		// handle empty tags (i.e. "<div/>")
		if ($this->copy_until_char('>') === '/') {
			$node->_[HDOM_INFO_ENDSPACE] .= '/';
			$node->_[HDOM_INFO_END] = 0;
		} elseif (!isset($this->self_closing_tags[strtolower($node->tag)])) {
			$this->parent = $node; // reset parent
		}

		$this->step();

		// A <br> carries the default text so plaintext can format line breaks
		if ($node->tag === 'br') {
			$node->_[HDOM_INFO_INNER] = $this->default_br_text;
		}

		return true;
	}

	/** Reads an attribute value. Returns false if the attribute is a duplicate (ignored). */
	protected function parse_attr($node, $name, &$space)
	{
		$is_duplicate = isset($node->attr[$name]);

		// Whitespace between "=" and value
		$ws = $this->copy_skip($this->token_blank);

		if (!$is_duplicate) { $space[2] = $ws; }

		switch ($this->char) {
			case '"':
				$quote_type = HDOM_QUOTE_DOUBLE;
				$this->step();
				$value = $this->copy_until_char('"');
				$this->step();
				break;
			case '\'':
				$quote_type = HDOM_QUOTE_SINGLE;
				$this->step();
				$value = $this->copy_until_char('\'');
				$this->step();
				break;
			default:
				$quote_type = HDOM_QUOTE_NO;
				$value = $this->copy_until($this->token_attr);
		}

		// Attributes should not have \r or \n in them, that counts as html whitespace.
		$value = str_replace(array("\r", "\n"), '', $this->restore_noise($value));

		// Get rid of the surrounding space in the "class" attribute
		if ($name === 'class') {
			$value = trim($value);
		}

		if ($is_duplicate) { return false; }

		$node->_[HDOM_INFO_QUOTE][] = $quote_type;
		$node->attr[$name] = $value;

		return true;
	}

	protected function link_nodes($node, $is_child)
	{
		$node->parent = $this->parent;
		$this->parent->nodes[] = $node;

		if ($is_child) {
			$this->parent->children[] = $node;
		}
	}

	protected function as_text_node($tag)
	{
		$node = new simple_html_dom_node($this);
		++$this->cursor;
		$node->_[HDOM_INFO_TEXT] = '</' . $tag . '>';
		$this->link_nodes($node, false);
		$this->step();

		return true;
	}

	protected function skip($chars)
	{
		$this->pos += strspn($this->doc, $chars, $this->pos);
		$this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null;
	}

	protected function copy_skip($chars)
	{
		$pos = $this->pos;
		$len = strspn($this->doc, $chars, $pos);
		$this->pos += $len;
		$this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null;

		return $len === 0 ? '' : substr($this->doc, $pos, $len);
	}

	protected function copy_until($chars)
	{
		$pos = $this->pos;
		$len = strcspn($this->doc, $chars, $pos);
		$this->pos += $len;
		$this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null;

		return substr($this->doc, $pos, $len);
	}

	protected function copy_until_char($char)
	{
		if ($this->char === null) { return ''; }

		if (($pos = strpos($this->doc, $char, $this->pos)) === false) {
			$ret = substr($this->doc, $this->pos, $this->size - $this->pos);
			$this->char = null;
			$this->pos = $this->size;
			return $ret;
		}

		if ($pos === $this->pos) { return ''; }

		$pos_old = $this->pos;
		$this->char = $this->doc[$pos];
		$this->pos = $pos;

		return substr($this->doc, $pos_old, $pos - $pos_old);
	}

	/**
	 * Replaces every match of $pattern with a noise key and stores the original
	 * text. Matches are processed in a single pass (no per-match copy of the
	 * document). With $remove_tag the whole match is stored, else only group 1.
	 */
	protected function remove_noise($pattern, $remove_tag = false)
	{
		$doc = preg_replace_callback(
			$pattern,
			function ($m) use ($remove_tag) {
				$text = $remove_tag ? $m[0][0] : $m[1][0];

				// Resolve keys of earlier passes now, so restoring never has to recurse
				if (strpos($text, '___noise___') !== false) {
					$text = $this->restore_noise($text);
				}

				$this->noise[] = $text;
				$key = '___noise___' . (count($this->noise) - 1) . '__';

				if ($remove_tag) { return $key; }

				$off = $m[1][1] - $m[0][1];

				return substr($m[0][0], 0, $off) . $key . substr($m[0][0], $off + strlen($m[1][0]));
			},
			$this->doc,
			-1,
			$count,
			PREG_OFFSET_CAPTURE
		);

		// null means a PCRE error (backtrack/JIT limit): keep the document as is
		if ($doc !== null) {
			$this->doc = $doc;
		}

		// reset the length of content
		$this->size = strlen($this->doc);
		$this->char = $this->size > 0 ? $this->doc[0] : null;
	}

	function restore_noise($text)
	{
		if (!is_string($text) || strpos($text, '___noise___') === false) {
			return $text;
		}

		// Single pass: unknown keys are left untouched (no endless loops)
		return preg_replace_callback(
			'/___noise___(\d+)__/',
			fn($m) => $this->noise[$m[1]] ?? $m[0],
			$text
		);
	}

	function search_noise($text)
	{
		simple_html_dom_node::_dbg(1);

		foreach ($this->noise as $noiseElement) {
			if (strpos($noiseElement, $text) !== false) {
				return $noiseElement;
			}
		}

		return null;
	}

	function __toString()
	{
		return $this->root->innertext();
	}

	function __get($name)
	{
		switch ($name) {
			case 'outertext':
			case 'innertext': return $this->root->innertext();
			case 'plaintext': return $this->root->text();
			case 'charset': return $this->_charset;
			case 'target_charset': return $this->_target_charset;
		}

		return null;
	}

	function childNodes($idx = -1)
	{
		return $this->root->childNodes($idx);
	}

	function firstChild()
	{
		return $this->root->first_child();
	}

	function lastChild()
	{
		return $this->root->last_child();
	}

	function createElement($name, $value = null)
	{
		$dom = str_get_html("<$name>$value</$name>");

		return $dom ? $dom->firstChild() : null;
	}

	function createTextNode($value)
	{
		$dom = str_get_html($value);

		return $dom ? $dom->nodes[array_key_last($dom->nodes)] : null;
	}

	function getElementById($id)
	{
		return $this->find("#$id", 0);
	}

	function getElementsById($id, $idx = null)
	{
		return $this->find("#$id", $idx);
	}

	function getElementByTagName($name)
	{
		return $this->find($name, 0);
	}

	function getElementsByTagName($name, $idx = null)
	{
		return $this->find($name, $idx);
	}

	function loadFile(...$args)
	{
		return $this->load_file(...$args);
	}
}

}
