<?php

namespace WebLoader\Filter;

/**
 * Remove all @charset 'utf8' and write only one at beginning of the file
 *
 * @author Mgr. Martin Jantošovič <martin.jantosovic@freya.sk>
 */
class CssCharsetFilter extends \Nette\Object {

	const CHARSET = '@charset "utf-8";';

	/**
	 * Invoke filter
	 * @param string code
	 * @param WebLoader loader
	 * @param string file
	 * @return string
	 */
	public function __invoke($code, \WebLoader\Compiler $loader, $file = null)
	{
		$regexp = '/@charset "utf\-8";(\n)?/';
		$code = \Nette\Utils\Strings::replace($code, $regexp);
		$code = self::CHARSET . "\n" . $code;

		return $code;
	}

}
