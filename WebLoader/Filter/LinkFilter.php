<?php

namespace WebLoader\Filter;

use \Nette\Utils\Strings,
	\Nette\Latte\Parser;

/**
 * Filter for replacing links for WebLoader
 *
 * @author Martin Jantošovič <martin.jantosovic@freya.sk>
 */
class LinkFilter {

	/** @var string */
	private $startVariable = '{{link';

	/** @var string */
	private $endVariable = '}}';

	/** @var array */
	private $variables;

	/** @var Nette\Application\IPresenter */
	private $presenter;

	/**
	 * Construct
	 * @param Nette\Application\IPresenter $presenter
	 * @param array $variables
	 */
	public function __construct(\Nette\Application\IPresenter $presenter) {
		$this->presenter = $presenter;
	}

	/**
	 * Set delimiter
	 * @param string $start
	 * @param string $end
	 * @return VariablesFilter
	 */
	public function setDelimiter($start, $end) {
		$this->startVariable = (string)$start;
		$this->endVariable = (string)$end;
		return $this;
	}

	/**
	 * Invoke filter
	 * @param string $code
	 * @return string
	 */
	public function __invoke($code) {
		$start = $this->startVariable;
		$end = $this->endVariable;
		$presenter = $this->presenter;

		$code = Strings::replace($code, '/' . preg_quote($start) . ' *([^ ]+)( .*?)? *' . preg_quote($end) . '/',
			function($match) use ($presenter) {
				$args = [];
				if (isset($match[2]) && $match[2]) {
					$argsMatch = Strings::matchAll($match[2], '/ *((' . Parser::RE_STRING . '|\w+) *=> *)?(' . Parser::RE_STRING . '|\w+) *,? */');
					foreach ($argsMatch as $m) {
						$value = trim(trim($m[3], "'"), '"');
						$key = $m[2] ? trim(trim($m[2], "'"), '"') : NULL;
						if ($key)
							$args[$key] = $value;
						else
							$args[] = $value;
					}
				}
				$destination = $match[1];
				return (string) $presenter->link($destination, $args);
			}
		);

		return $code;
	}

}
