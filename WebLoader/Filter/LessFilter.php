<?php

namespace WebLoader\Filter;

/**
 * Convert LESS to CSS
 *
 * @author Mgr. Martin Jantošovič <martin.jantosovic@freya.sk>
 */
class LessFilter extends \Nette\Object {

	const RE_STRING = '\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"';

	/** @var path to lessc bin */
	private $bin;

	/**
	 * @param string
	 */
	public function __construct($bin = 'lessc')
	{
		$this->bin = $bin;
	}

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

		$dir = dirname($file);
		$dependencies = [];
		foreach (\Nette\Utils\Strings::matchAll($code, '/@import ('.self::RE_STRING.');/') as $match) {
			$dependedFile = $dir . '/' . substr($match[1], 1, strlen($match[1]) - 2);
			if (is_file($dependedFile))
				$dependencies[] = $dependedFile;
		}
		if ($dependencies)
			$loader->setDependedFiles($file, $dependencies);

		$code = $this->compileLess($code, $info['dirname']);
		return $code;
	}

	/**
	 * @param string
	 * @param bool|NULL
	 * @return string
	 */
	public function compileLess($code, $includePath = NULL) {
		$cmd = $this->bin . ($includePath ? " --include-path='$includePath'" : '') . ' --no-color -';

		return Process::run($cmd, $code);
	}

}
