<?php

namespace WebLoader\Filter;

/**
 * Convert LESS to CSS
 *
 * Add to composer.json "https://github.com/Mordred/less.php"
 *
 * @author Mgr. Martin Jantošovič <martin.jantosovic@freya.sk>
 */
class LessFilter extends \Nette\Object {

	/**
	 * Invoke filter
	 * @param string code
	 * @param WebLoader loader
	 * @param string file
	 * @return string
	 */
	public function __invoke($code, \WebLoader\Compiler $loader, $file = null)
	{
		$info = pathinfo($file);
		// Iba na LESS subory
		if (strtolower($info['extension']) != 'less') {
			return $code;
		}

		// Create our environment
		$env = new \Less\Environment;
		$env->setCompress(false);

		// parse the selected files (or stdin if '-' is given)
		$parser = new \Less\Parser($env);
		$parser->parse($code, FALSE, $file);

		$code = $parser->getCss();

		return $code;
	}

}
