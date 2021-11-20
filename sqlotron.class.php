<?php
define('DIGIT', '0123456789');
define('ALPHA', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
define('PAREN', '()');
define('SINGLEQUOTE', "'");
define('DOUBLEQUOTE', '"');
define('UNDERSCORE', '_');
define('PERIOD', '.');
define('OPERATOR', '+-*/=!<>');
define('WHITESPACE', " \t\r\n");
define('COMMA', ',');
define('SEMICOLON', ';');

if (!function_exists('str_contains')) {
	function str_contains($haystack, $needle) {
		return strpos($haystack, $needle) !== false;
	}
}

class Token
{
	const KEYWORDS = [
		'select',
		'from',
		'left',
		'right',
		'full',
		'outer',
		'inner',
		'where',
		'having',
		'order',
		'by',
		'limit',
		'offset',
	];
	const OPERATORS = [
		'and', 'or'
	];
	var $type;
	var $value;
	var $group;
	var $lc;
	function __construct($tokenGroup, $value)
	{
		$this->group = $tokenGroup;
		$this->type = $this->_getTokenType($tokenGroup);
		$this->value = $value;
		$this->lc = strtolower($value);
	}
	function _getTokenType()
	{
		if ($this->group === 'singlequotestring' || $this->group === 'doublequotestring') {
			return 'string';
		} elseif ($this->group === 'symbol') {
			if (in_array(strtolower($this->value), self::KEYWORDS)) {
				return 'keyword';
			}
			if (in_array(strtolower($this->value), self::OPERATORS)) {
				return 'operator';
			}
			return 'symbol';
		} elseif ($this->group === 'numberliteral') {
			return 'number';
		} elseif ($this->group === 'paren') {
			return 'paren';
		} else {
			return $this->group;
		}
	}
}

class SQLBreakDown
{
	function __construct()
	{
		$this->TOKENTYPES = [
			'undetermined' => [
				'starter' => '',
			],
			'singlequotestring' => [
				'starter' => "'",
			],
			'doublequotestring' => [
				'starter' => '"',
			],
			'backticksymbol' => [
				'starter' => '`',
			],
			'symbol' => [
				'starter' => ALPHA,
			],
			'operator' => [
				'starter' => OPERATOR,
			],
			'whitespace' => [
				'starter' => WHITESPACE,
			],
			'comma' => [
				'starter' => COMMA,
			],
			'semicolon' => [
				'starter' => SEMICOLON,
			],
			'paren' => [
				'starter' => PAREN,
			],
			'numberliteral' => [
				'starter' => DIGIT . PERIOD,
			],
		];
	}

	public function tokenize($sql)
	{
		// on convertit la chaîne SQL en un itérateur sur tableau de caractères
		// car plus pratique à faire circuler entre les fonctions.
		// $sqlChars = (new ArrayObject(str_split($sql)))->getIterator();
		// $this->sqlChars = $sqlChars;
		$this->sql = $sql;
		$this->cursor = 0;
		$this->tokens = [];
		while ($this->_current()) {
			$nextToken = $this->_getNextToken();
			$this->tokens[] = $nextToken;
		}
		return $this->tokens;
	}

	/**
	 * 
	 */
	public function parse($sql)
	{
		$this->tokenize($sql);
		$ret = [
			'select' =>   [],
			'from' =>     [],
			'where' =>    [],
			'having' =>   [],
			'orderby' =>  [],
			'limit' =>    [],
			'offset' =>   [],
		];
		$tokenN = 0;
		$findClosing = function () use (&$tokenN, &$findClosing) {
			$tokenN++;
			$r = [];
			while ($tokenN < count($this->tokens)) {
				$token = $this->tokens[$tokenN++];
				$r[] = $token;
				if ($token->value === ')') {
					$tokenN--;
					return $r;
				} elseif ($token->value === '(') {
					return array_merge($r, $findClosing());
				}
			}
			return $r;
		};
		$nextNonWhiteSpace = function () use (&$tokenN) {
			$n = $tokenN + 1;
			// on fait avancer $n tant qu'on a des espaces et qu'il reste des jetons
			while ($n < count($this->tokens) && $this->tokens[$n++]->type === 'whitespace');
			if ($n >= count($this->tokens)) return null; // plus de jetons
			return $this->tokens[$n-1];
		};

		$currentBucket = 'select';
		while ($tokenN < count($this->tokens)) {
			$token = $this->tokens[$tokenN];
			if ($token->type === 'whitespace') {
				$ret[$currentBucket][] = $token;
			} elseif ($token->lc === 'select') {
				$ret[$currentBucket][] = $token;
			} elseif ($token->value === '(') {
				$ret[$currentBucket][] = $token;
				$ret[$currentBucket] = array_merge($ret[$currentBucket], $findClosing());
			} elseif ($token->lc === 'from') {
				$currentBucket = 'from';
				$ret[$currentBucket][] = $token;
			} elseif ($token->lc === 'where') {
				$currentBucket = 'where';
				$ret[$currentBucket][] = $token;
			} elseif ($token->lc === 'having') {
				$currentBucket = 'having';
				$ret[$currentBucket][] = $token;
			} elseif ($token->lc === 'order') {
				$x = $nextNonWhiteSpace();
				if ($x->lc === 'by') {$currentBucket = 'orderby';} // pas très élégant
				$ret[$currentBucket][] = $token;
			} elseif ($token->lc === 'limit') {
				$currentBucket = 'limit';
				$ret[$currentBucket][] = $token;
			} elseif ($token->lc === 'offset') {
				$currentBucket = 'offset';
				$ret[$currentBucket][] = $token;
			} else {
				$ret[$currentBucket][] = $token;
			}
			$tokenN++;
		}
		$tokenVal = function ($token) { return $token->value; };
		$this->buckets = $ret;
		foreach ($ret as $bucketName => $bucket) {
			$this->{$bucketName} = implode('', array_map($tokenVal, $bucket));
		}
	}
	private function _determine($c)
	{
		foreach ($this->TOKENTYPES as $tokentype => $tokentypedef) {
			if (str_contains($tokentypedef['starter'], $c)) {
				return $tokentype;
			}
		}
		return 'undetermined';
	}
	private function _getNextToken()
	{
		$c = $this->_current();
		$curTokenGroup = $this->_determine($c);
		$curTokenValue = $c;
		switch($curTokenGroup) {
			case 'undetermined':
				$curTokenValue .= $this->_next();
				return new Token($curTokenGroup, $curTokenValue);
			case 'singlequotestring': // TODO: factoriser + debug quand double (ça triple???)
				while ($this->_current() !== '') {
					$curTokenValue .= $c = $this->_next();
					if ($c === "'") {
						if ($this->_next() === "'") {
							$curTokenValue .= "'";
							$this->_next();
						} else {
							// $curTokenValue .= $c;
							// $this->_prev();
							return new Token($curTokenGroup, $curTokenValue);
						}
					} elseif ($c === "\\") {
						if ($this->_next() === "'") {
							$curTokenValue .= "'";
						} else {
							$curTokenValue .= $c;
							return new Token($curTokenGroup, $curTokenValue);
						}
					}
				}
				throw $this->_tokenError();
			case 'doublequotestring': // TODO factoriser avec singlequotestring
				while ($this->_current() !== '') {
					$curTokenValue .= $c = $this->_next();
					if ($c === '"') {
						if ($this->_next() === '"') {
							$curTokenValue .= '"';
						} else {
							// $curTokenValue .= $c;
							// $this->_prev();
							return new Token($curTokenGroup, $curTokenValue);
						}
					} elseif ($c === "\\") {
						if ($this->_next() === '"') {
							$curTokenValue .= '"';
						} else {
							$curTokenValue .= $c;
							return new Token($curTokenGroup, $curTokenValue);
						}
					}
				}
				throw $this->_tokenError();
			case 'backticksymbol':
				while ($this->_current() !== '') {
					$c = $this->_next();
					if ($c === '`') {
						$curTokenValue .= $c;
						$this->_next();
						return new Token($curTokenGroup, $curTokenValue);
					} else {
						$curTokenValue .= $c;
					}
				}
				throw $this->_tokenError();
			case 'symbol':
				while ($this->_current() !== '') {
					$c = $this->_next();
					if (str_contains(ALPHA . DIGIT . UNDERSCORE . PERIOD, $c)) {
						$curTokenValue .= $c;
					} else {
						return new Token($curTokenGroup, $curTokenValue);
					}
				}
				return new Token($curTokenGroup, $curTokenValue);
			case 'operator':
				while ($this->_current() !== '') {
					$c = $this->_next();
					if (str_contains(OPERATOR, $c)) {
						$curTokenValue .= $c;
					} else {
						return new Token($curTokenGroup, $curTokenValue);
					}
				}
				throw $this->_tokenError();
			case 'whitespace':
				while ($this->_current() !== '') {
					$c = $this->_next();
					if (str_contains(WHITESPACE, $c)) {
						$curTokenValue .= $c;
					} else {
						return new Token($curTokenGroup, $curTokenValue);
					}
				}
				return new Token($curTokenGroup, $curTokenValue);
			case 'semicolon':
				$this->_next();
				return new Token($curTokenGroup, $curTokenValue);
			case 'comma':
				$this->_next();
				return new Token($curTokenGroup, $curTokenValue);
			case 'paren':
				$this->_next();
				return new Token($curTokenGroup, $curTokenValue);
			case 'numberliteral':
				while ($this->_current() !== '') {
					$c = $this->_next();
					if (str_contains(DIGIT . PERIOD, $c)) {
						$curTokenValue .= $c;
					} else {
						return new Token($curTokenGroup, $curTokenValue);
					}
				}
				return new Token($curTokenGroup, $curTokenValue);
			default:
				throw $this->_tokenError();
		}
	}
	private function _current() {
		return mb_substr($this->sql, $this->cursor, 1);
	}
	private function _next() {
		$this->cursor++;
		return $this->_current();
	}
	private function _prev() {
		$this->cursor--;
		return $this->_current();
	}
	private function _tokenError() {
		return new Exception('TokenizeError');
	}
	public function serializeBuckets() {
		$ret = [];
		foreach ($this->buckets as $name => $bucket) {
			$ret[$name] = $this->{$name};
		}
		return $ret;
	}
	public function getSQL() {
		return implode(' ', array_values($this->serializeBuckets()));
	}
}

